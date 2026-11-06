<?php

namespace Tests\Feature\Mail;

use App\Enums\InterpretationOutcome;
use App\Enums\ProcessingStatus;
use App\Enums\SenderAccess;
use App\Enums\SourceKind;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\GmailClient;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Knowledge\Review\DocumentReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

/**
 * An attachment is a document like an upload: one table, one check. A file
 * the knowledge base has is never processed again; the mail that sent it
 * again becomes one more source of it.
 */
class MailAttachmentTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private const string PDF = '%PDF-1.4 verlofregeling 2027';

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);
        Http::fake([
            GmailClient::BASE_URL.'/messages/*/attachments/*' => Http::response(['data' => GmailFixtures::encode(self::PDF), 'size' => strlen(self::PDF)]),
            GmailClient::BASE_URL.'/messages/send' => Http::response(['id' => 'reply-1', 'threadId' => 'thread-1']),
        ]);

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']));
        $this->fakeKnowledgeAi();
    }

    public function test_an_attachment_judged_to_be_knowledge_becomes_a_document_waiting_for_approval(): void
    {
        $this->classifierSays([['id' => 'A1', 'use' => 'knowledge', 'reason' => 'De nieuwe verlofregeling.']]);

        $email = $this->email('m1', [$this->pdf()]);
        $this->interpret($email);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, '<attachment id="A1" type="pdf"')
            && str_contains($prompt->prompt, 'Verlofregeling 2027.pdf'));

        Tenancy::for($this->account, function () use ($email) {
            $version = DocumentVersion::query()->with('document')->sole();
            $this->assertSame('Verlofregeling 2027', $version->document->title);
            $this->assertSame(ProcessingStatus::Review, $version->status);
            $this->assertSame(hash('sha256', self::PDF), $version->sha256);
            $this->assertTrue($version->isFromMail());

            $source = DocumentSource::query()->sole();
            $this->assertSame(SourceKind::Mail, $source->kind);
            $this->assertSame($email->id, $source->email_id);
            $this->assertStringContainsString('De nieuwe verlofregeling.', (string) $source->context);

            $attachment = $email->fresh()?->attachments[0];
            $this->assertSame('new', $attachment['decision'] ?? null);
            $this->assertSame($version->id, $attachment['document_version_id'] ?? null);

            // Its facts are proposed, like a mail's. The document waits in
            // the knowledge base; the mail itself does not, and reads as
            // having proposed something.
            $this->assertTrue($version->facts()->exists());
            $interpretation = EmailInterpretation::query()->where('email_id', $email->id)->sole();
            $this->assertFalse($interpretation->needs_review);
            $this->assertSame(InterpretationOutcome::Added, $interpretation->outcome);
        });
    }

    public function test_the_mailed_document_waits_in_the_approvals(): void
    {
        $this->classifierSays([['id' => 'A1', 'use' => 'knowledge', 'reason' => '']]);
        $email = $this->email('m1', [$this->pdf()]);
        $this->interpret($email);

        $this->actingAs(User::factory()->withAccount($this->account)->create())
            ->get(route('knowledge.approvals'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.0.title', 'Verlofregeling 2027')
                ->where('documents.0.sources.0.emailId', $email->id)
                ->has('mails', 0));
    }

    public function test_an_attachment_judged_not_to_be_knowledge_stays_out(): void
    {
        $this->classifierSays([['id' => 'A1', 'use' => 'ignore', 'reason' => 'Een factuur.']]);

        $email = $this->email('m1', [$this->pdf('Factuur 2026-118.pdf')]);
        $this->interpret($email);

        Tenancy::for($this->account, function () use ($email) {
            $this->assertSame(0, Document::query()->count());
            $attachment = $email->fresh()?->attachments[0];
            $this->assertSame('ignored', $attachment['decision'] ?? null);
            $this->assertSame('Een factuur.', $attachment['reason'] ?? null);
            $this->assertSame(InterpretationOutcome::NoAction, EmailInterpretation::query()->where('email_id', $email->id)->value('outcome'));
        });
    }

    public function test_attachments_from_a_sender_howy_may_only_answer_stay_out(): void
    {
        $this->classifierSays([['id' => 'A1', 'use' => 'knowledge', 'reason' => 'De nieuwe verlofregeling.']]);

        $email = $this->email('m1', [$this->pdf()]);
        Tenancy::for($this->account, fn () => EmailInterpretation::query()->create(['email_id' => $email->id, 'sender_access' => SenderAccess::Answer]));
        $this->interpret($email);

        Tenancy::for($this->account, function () use ($email) {
            $this->assertSame(0, Document::query()->count());
            // Undecided, so an administrator can still add it by hand.
            $this->assertNull($email->fresh()?->attachments[0]['decision'] ?? null);
            $this->assertSame(InterpretationOutcome::NoAction, EmailInterpretation::query()->where('email_id', $email->id)->value('outcome'));
        });
    }

    public function test_a_file_sent_again_maps_the_new_thread_to_the_existing_document(): void
    {
        $this->classifierSays([['id' => 'A1', 'use' => 'knowledge', 'reason' => '']]);
        $this->interpret($this->email('m1', [$this->pdf()]));

        // Forwarded in a thread of its own, under another name.
        $forward = $this->email('fwd', [$this->pdf('verlof (1).pdf')], ['provider_thread_id' => 'thread-fwd', 'subject' => 'Fwd: Verlof', 'content_text' => 'Zie bijlage, dit is de regeling waar ik het over had.']);
        $this->interpret($forward);

        EmailClassifier::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'known="Verlofregeling 2027 v1"'));

        Tenancy::for($this->account, function () use ($forward) {
            $this->assertSame(1, DocumentVersion::query()->count());
            $this->assertSame(2, DocumentSource::query()->count());
            $this->assertTrue(DocumentSource::query()->where('email_id', $forward->id)->where('filename', 'verlof (1).pdf')->exists());
            $this->assertSame('known', $forward->fresh()?->attachments[0]['decision'] ?? null);
            $this->assertSame(InterpretationOutcome::Duplicate, EmailInterpretation::query()->where('email_id', $forward->id)->value('outcome'));
        });

        // Read once: the pipeline ran for the first copy only.
        Process::assertRanTimes(fn ($process) => str_contains(implode(' ', (array) $process->command), 'pdftohtml'), 1);
    }

    public function test_a_rejected_file_sent_again_stays_rejected(): void
    {
        $this->classifierSays([['id' => 'A1', 'use' => 'knowledge', 'reason' => '']]);
        $this->interpret($this->email('m1', [$this->pdf()]));
        Tenancy::for($this->account, fn () => app(DocumentReview::class)->reject(DocumentVersion::query()->sole()));

        $again = $this->email('again', [$this->pdf()], ['provider_thread_id' => 'thread-2']);
        $this->interpret($again);

        Tenancy::for($this->account, function () use ($again) {
            $this->assertSame(ProcessingStatus::Rejected, DocumentVersion::query()->sole()->status);
            $this->assertSame('known', $again->fresh()?->attachments[0]['decision'] ?? null);
            $this->assertFalse(EmailInterpretation::query()->where('email_id', $again->id)->value('needs_review'));
        });
    }

    public function test_what_cannot_be_read_is_set_aside_without_downloading(): void
    {
        $this->classifierSays([]);

        $email = $this->email('m1', [
            ['filename' => 'logo.png', 'mime_type' => 'image/png', 'size' => 4_000, 'attachment_id' => 'att-logo', 'part_id' => '2', 'content_id' => 'logo', 'is_inline' => true],
            ['filename' => 'archief.zip', 'mime_type' => 'application/zip', 'size' => 90_000, 'attachment_id' => 'att-zip', 'part_id' => '3', 'content_id' => null, 'is_inline' => false],
            ['filename' => 'video.pdf', 'mime_type' => 'application/pdf', 'size' => 40_000_000, 'attachment_id' => 'att-big', 'part_id' => '4', 'content_id' => null, 'is_inline' => false],
            ['filename' => 'Origineel.eml', 'mime_type' => 'message/rfc822', 'size' => 9_000, 'attachment_id' => 'att-eml', 'part_id' => '5', 'content_id' => null, 'is_inline' => false],
        ]);
        $this->interpret($email);

        $this->assertSame(
            ['decorative', 'unsupported', 'too_large', 'forwarded'],
            array_column(Tenancy::for($this->account, fn () => $email->fresh()?->attachments ?? []), 'decision'),
        );
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/attachments/'));
    }

    public function test_an_administrator_adds_an_ignored_attachment_by_hand(): void
    {
        $this->classifierSays([['id' => 'A1', 'use' => 'ignore', 'reason' => '']]);
        $email = $this->email('m1', [$this->pdf()]);
        $this->interpret($email);

        $admin = User::factory()->withAccount($this->account)->create();
        $this->actingAs($admin)
            ->post(route('emails.attachments.store', [$email, 0]))
            ->assertRedirect();

        Tenancy::for($this->account, function () use ($email) {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Review, $version->status);
            $this->assertSame('new', $email->fresh()?->attachments[0]['decision'] ?? null);
        });
    }

    public function test_only_administrators_add_attachments(): void
    {
        $email = $this->email('m1', [$this->pdf()]);
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)->post(route('emails.attachments.store', [$email, 0]))->assertForbidden();
        $this->actingAs(User::factory()->withAccount($this->account)->create())->post(route('emails.attachments.store', [$email, 9]))->assertNotFound();
    }

    /**
     * @param  list<array{id: string, use: string, reason: string}>  $attachments
     */
    private function classifierSays(array $attachments): void
    {
        $this->fakeMailAi(['intent' => 'information', 'summary' => 'Tom stuurt de nieuwe verlofregeling.', 'statements' => [], 'attachments' => $attachments]);
    }

    /**
     * @return array{filename: string, mime_type: string, size: int, attachment_id: string, part_id: string, content_id: null, is_inline: false}
     */
    private function pdf(string $filename = 'Verlofregeling 2027.pdf'): array
    {
        return ['filename' => $filename, 'mime_type' => 'application/pdf', 'size' => 84_000, 'attachment_id' => 'att-'.md5($filename), 'part_id' => '1', 'content_id' => null, 'is_inline' => false];
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $attributes
     */
    private function email(string $id, array $attachments, array $attributes = []): Email
    {
        return Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_message_id' => $id,
            'provider_thread_id' => 'thread-1',
            'message_id_header' => "<{$id}@mail.gmail.com>",
            'subject' => 'Verlof',
            'from_name' => 'Tom Bakker',
            'from_email' => 'tom@haarlem.nl',
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'content_text' => 'Bijgaand de nieuwe verlofregeling voor volgend jaar, graag opnemen in de kennisbank.',
            'attachments' => $attachments,
            'has_attachments' => true,
            ...$attributes,
        ]));
    }

    private function interpret(Email $email): void
    {
        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id));
    }
}
