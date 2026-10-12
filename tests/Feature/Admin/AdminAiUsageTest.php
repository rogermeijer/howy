<?php

namespace Tests\Feature\Admin;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AiUsageRecord;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\DocumentSummarizer;
use App\Services\Knowledge\Ai\AiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * The admin AI log reads across accounts — the sanctioned exception in
 * App\Services\Admin\AiUsageLog — and only for super-admins.
 */
class AdminAiUsageTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private User $admin;

    private Account $ours;

    private Account $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeKnowledgeAi();
        $this->admin = User::factory()->superAdmin()->withAccount()->create();
        $this->ours = $this->admin->accounts()->sole();
        $this->theirs = Account::factory()->create(['name' => 'Their Co']);

        Tenancy::for($this->ours, fn () => app(AiGateway::class)->embed(['ours'], 'search'));
        Tenancy::for($this->theirs, function (): void {
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id, 'provider_thread_id' => 'thread-7', 'subject' => 'Holiday question']);
            app(AiGateway::class)->structured(new DocumentSummarizer, 'Theirs', 'mail_classify', $email);
            app(AiGateway::class)->structured(new DocumentSummarizer, 'Theirs again', 'mail_answer', $email);
        });
    }

    public function test_the_log_shows_calls_from_every_account(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.ai.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ai/index')
                ->has('calls.data', 3)
                ->where('totals.calls', 3)
                ->has('breakdowns.account', 2)
                ->where('breakdowns.thread.0.label', 'Holiday question')
                ->where('breakdowns.thread.0.calls', 2)
                ->where('breakdowns.thread.0.filter', ['account' => $this->theirs->id, 'thread' => 'thread-7'])
                ->has('daily', 31));
    }

    public function test_the_log_filters(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.ai.index', ['account' => $this->theirs->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('calls.data', 2)
                ->where('calls.data.0.account.name', 'Their Co')
                ->where('calls.data.0.subject_label', 'Holiday question'));

        $this->get(route('admin.ai.index', ['step' => 'search']))
            ->assertInertia(fn (Assert $page) => $page->has('calls.data', 1)->where('calls.data.0.agent', 'embeddings'));

        $this->get(route('admin.ai.index', ['account' => $this->theirs->id, 'thread' => 'thread-7', 'status' => 'failed']))
            ->assertInertia(fn (Assert $page) => $page->has('calls.data', 0)->where('totals.calls', 0));
    }

    public function test_a_call_from_another_account_opens_with_its_payload(): void
    {
        $id = Tenancy::for($this->theirs, fn () => AiUsageRecord::query()->where('step', 'mail_answer')->sole()->id);

        $this->actingAs($this->admin)
            ->get(route('admin.ai.show', $id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ai/show')
                ->where('call.account.name', 'Their Co')
                ->where('call.payload.input', 'Theirs again'));
    }

    public function test_an_unknown_call_404s(): void
    {
        $this->actingAs($this->admin)->get(route('admin.ai.show', 999999))->assertNotFound();
    }

    public function test_an_account_admin_cannot_read_the_log(): void
    {
        $id = Tenancy::for($this->theirs, fn () => AiUsageRecord::query()->first()?->id);

        $this->actingAs(User::factory()->withAccount($this->ours)->create());

        $this->get(route('admin.ai.index'))->assertForbidden();
        $this->get(route('admin.ai.show', $id))->assertForbidden();
    }
}
