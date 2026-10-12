<?php

namespace Tests\Feature\Knowledge;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AiCallPayload;
use App\Models\AiUsageRecord;
use App\Models\Email;
use App\Models\Mailbox;
use App\Services\Knowledge\Ai\Agents\DocumentSummarizer;
use App\Services\Knowledge\Ai\AiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Every AI call is logged: tokens and cost for good, what was sent and
 * received encrypted and only for a while.
 */
class AiCallLogTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeKnowledgeAi();
        $this->account = Account::factory()->create();
    }

    public function test_a_call_is_logged_with_its_payload(): void
    {
        Tenancy::for($this->account, function (): void {
            app(AiGateway::class)->structured(new DocumentSummarizer, 'Document: Handbook', 'summary');

            $record = AiUsageRecord::query()->with('payload')->sole();

            $this->assertSame('DocumentSummarizer', $record->agent);
            $this->assertSame(AiUsageRecord::SUCCEEDED, $record->status);
            $this->assertNotNull($record->latency_ms);
            $this->assertNotNull($record->payload);
            $this->assertSame('Document: Handbook', $record->payload->input);
            $this->assertStringContainsString('Samenvatting van het document.', (string) $record->payload->output);
            $this->assertStringContainsString('company document', (string) $record->payload->instructions);

            // Encrypted at rest.
            $raw = AiCallPayload::query()->toBase()->value('input');
            $this->assertIsString($raw);
            $this->assertStringNotContainsString('Handbook', $raw);
        });
    }

    public function test_embeddings_are_logged_with_their_inputs(): void
    {
        Tenancy::for($this->account, function (): void {
            app(AiGateway::class)->embed(['one', 'two'], 'embed');

            $record = AiUsageRecord::query()->with('payload')->sole();

            $this->assertSame('embeddings', $record->agent);
            $this->assertSame(2, $record->input_count);
            $this->assertSame(20, $record->input_tokens);
            $this->assertStringContainsString('"two"', (string) $record->payload?->input);
        });
    }

    public function test_a_failed_call_is_logged_and_rethrown(): void
    {
        DocumentSummarizer::fake(fn () => throw new RuntimeException('Provider down'));

        Tenancy::for($this->account, function (): void {
            try {
                app(AiGateway::class)->structured(new DocumentSummarizer, 'Document: Handbook', 'summary');
                $this->fail('The failure should have been rethrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('Provider down', $e->getMessage());
            }

            $record = AiUsageRecord::query()->sole();

            $this->assertSame(AiUsageRecord::FAILED, $record->status);
            $this->assertSame('Provider down', $record->error);
            $this->assertSame(0, $record->input_tokens);
            $this->assertSame(0, $record->estimated_cost_micros);
        });
    }

    public function test_payloads_can_be_switched_off(): void
    {
        config(['knowledge.ai_log.payloads' => false]);

        Tenancy::for($this->account, function (): void {
            app(AiGateway::class)->structured(new DocumentSummarizer, 'Document: Handbook', 'summary');

            $this->assertSame(1, AiUsageRecord::query()->count());
            $this->assertSame(0, AiCallPayload::query()->count());
        });
    }

    public function test_a_mail_call_remembers_its_thread(): void
    {
        Tenancy::for($this->account, function (): void {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id, 'provider_thread_id' => 'thread-42']);

            app(AiGateway::class)->structured(new DocumentSummarizer, 'Hello', 'mail_classify', $email);

            $this->assertSame('thread-42', AiUsageRecord::query()->sole()->thread_key);
        });
    }

    public function test_a_dated_model_snapshot_is_priced_as_its_alias(): void
    {
        $gateway = app(AiGateway::class);

        $this->assertSame(
            $gateway->costMicros('gpt-6-luna', 1000, 0, 100),
            $gateway->costMicros('gpt-6-luna-2026-08-01', 1000, 0, 100),
        );
        $this->assertSame(0, $gateway->costMicros('unknown-model', 1000, 0, 100));
    }

    public function test_old_payloads_are_pruned_and_the_records_stay(): void
    {
        $other = Account::factory()->create();

        foreach ([$this->account, $other] as $account) {
            Tenancy::for($account, function (): void {
                app(AiGateway::class)->structured(new DocumentSummarizer, 'Old', 'summary');
                AiCallPayload::query()->update(['created_at' => now()->subDays(31)]);
                app(AiGateway::class)->structured(new DocumentSummarizer, 'New', 'summary');
            });
        }

        $this->artisan('knowledge:prune-ai-payloads')->assertSuccessful();

        foreach ([$this->account, $other] as $account) {
            Tenancy::for($account, function (): void {
                $this->assertSame(2, AiUsageRecord::query()->count());
                $this->assertSame(['New'], AiCallPayload::query()->get()->pluck('input')->all());
            });
        }
    }

    public function test_another_accounts_payloads_are_invisible(): void
    {
        $other = Account::factory()->create();

        Tenancy::for($other, fn () => app(AiGateway::class)->structured(new DocumentSummarizer, 'Theirs', 'summary'));

        Tenancy::for($this->account, function (): void {
            $this->assertSame(0, AiCallPayload::query()->count());
            $this->assertSame(0, AiUsageRecord::query()->count());
        });
    }
}
