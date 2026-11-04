<?php

namespace Tests\Feature\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\SectionChange;
use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\GmailClient;
use App\Services\Knowledge\Ai\Agents\ImageInterpreter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ApprovesDocuments;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

/**
 * A file is held against the documents already there before anything costs
 * a token: the same text under another file, or a new version of a document
 * that shares its sections or its name.
 */
class DocumentPlacementTest extends TestCase
{
    use ApprovesDocuments, FakesKnowledgeAi, RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    private string $xml;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('knowledge');
        $this->fakeKnowledgeAi();
        $this->xml = (string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'));
        $this->fakePoppler($this->xml);

        $this->account = Account::factory()->create(['locale' => 'nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']));
        $this->fakeMailAi(['intent' => 'information', 'statements' => [], 'attachments' => [['id' => 'A1', 'use' => 'knowledge', 'reason' => '']]]);
    }

    public function test_a_mailed_file_that_shares_most_sections_becomes_the_next_version(): void
    {
        $handbook = $this->upload('handboek v1', 'personeelshandboek.pdf');
        $this->approvePending($this->account, $this->admin);

        $this->fakePoppler(str_replace('vóór 09:00 uur telefonisch', 'vóór 08:30 uur telefonisch', $this->xml));
        $this->mail('m1', 'Personeelshandboek 2027 (definitief).pdf', '%PDF-1.4 handboek 2027');

        Tenancy::for($this->account, function () use ($handbook) {
            $this->assertSame(1, Document::query()->count());

            $v2 = DocumentVersion::query()->where('version_number', 2)->sole();
            $this->assertSame($handbook->id, $v2->document_id);
            $this->assertSame(ProcessingStatus::Review, $v2->status);
            $this->assertSame('version', $v2->placement['kind'] ?? null);
            $this->assertStringStartsWith("accounts/{$this->account->id}/documents/{$handbook->id}/v2/", $v2->path);
            Storage::disk('knowledge')->assertExists($v2->path);

            // Compared with what is live: only the changed section is new work.
            $changes = DocumentSection::query()->where('document_version_id', $v2->id)->pluck('change_type', 'heading')->all();
            $this->assertSame(SectionChange::Changed, $changes['3 Ziekmelding']);
            $this->assertSame(SectionChange::Unchanged, $changes['2.1 Vakantiedagen']);
            $enrich = DocumentProcessingStep::query()->where('document_version_id', $v2->id)->where('step', ProcessingStep::Enrich)->sole();
            $this->assertSame(1, $enrich->meta['requests'] ?? null);

            $this->assertTrue(DocumentSource::query()->where('document_version_id', $v2->id)->whereNotNull('email_id')->exists());
        });
    }

    public function test_the_same_text_in_another_file_maps_the_mail_to_the_existing_version(): void
    {
        $this->upload('handboek v1', 'personeelshandboek.pdf');
        $this->approvePending($this->account, $this->admin);

        // Exported again: other bytes, same text.
        $email = $this->mail('m1', 'handboek-export.pdf', '%PDF-1.4 opnieuw geëxporteerd');

        Tenancy::for($this->account, function () use ($email) {
            $v1 = DocumentVersion::query()->sole();
            $this->assertSame(1, $v1->version_number);
            $this->assertTrue(DocumentSource::query()->where('document_version_id', $v1->id)->where('email_id', $email->id)->exists());

            $attachment = $email->fresh()?->attachments[0];
            $this->assertSame('known', $attachment['decision'] ?? null);
            $this->assertSame($v1->id, $attachment['document_version_id'] ?? null);
        });
    }

    public function test_an_upload_is_never_moved_but_the_reviewer_is_told(): void
    {
        $this->upload('handboek v1', 'personeelshandboek.pdf');
        $this->approvePending($this->account, $this->admin);

        $this->fakePoppler(str_replace('vóór 09:00 uur telefonisch', 'vóór 08:30 uur telefonisch', $this->xml));
        $copy = $this->upload('handboek kopie', 'personeelshandboek-2027.pdf');

        Tenancy::for($this->account, function () use ($copy) {
            $this->assertSame(2, Document::query()->count());

            $placement = $copy->latestVersion()->sole()->placement;
            $this->assertSame('similar', $placement['kind'] ?? null);
            $this->assertSame(Document::query()->orderBy('id')->value('id'), $placement['document_id'] ?? null);
        });
    }

    public function test_a_mailed_image_that_is_only_decoration_is_turned_down_and_remembered(): void
    {
        $this->fakeMailAi(['intent' => 'other', 'statements' => [], 'attachments' => [['id' => 'A1', 'use' => 'knowledge', 'reason' => '']]]);
        $logo = (string) UploadedFile::fake()->image('logo.png', 400, 200)->get();

        $first = $this->mail('m1', 'logo.png', $logo, 'image/png', 90_000);
        $again = $this->mail('m2', 'logo.png', $logo, 'image/png', 90_000);

        Tenancy::for($this->account, function () use ($first, $again) {
            $version = DocumentVersion::query()->sole();
            $this->assertSame(ProcessingStatus::Rejected, $version->status);
            $this->assertSame(0, Document::query()->listed()->count());

            $this->assertSame('ignored', $first->fresh()?->attachments[0]['decision'] ?? null);
            $this->assertSame('known', $again->fresh()?->attachments[0]['decision'] ?? null);
        });

        // Read once.
        ImageInterpreter::assertPromptedTimes(1);
    }

    private function upload(string $body, string $filename): Document
    {
        $this->actingAs($this->admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent($filename, '%PDF-1.4 '.$body),
            'type' => 'handbook',
            'is_core' => true,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();

        return Tenancy::for($this->account, fn () => Document::query()->latest('id')->firstOrFail());
    }

    private function mail(string $id, string $filename, string $bytes, string $mime = 'application/pdf', int $size = 84_000): Email
    {
        Http::fake([
            GmailClient::BASE_URL."/messages/{$id}/attachments/*" => Http::response(['data' => GmailFixtures::encode($bytes)]),
        ]);

        $email = Tenancy::for($this->account, fn () => Email::factory()->create([
            'mailbox_id' => $this->mailbox->id,
            'provider_message_id' => $id,
            'provider_thread_id' => "thread-{$id}",
            'message_id_header' => "<{$id}@mail.gmail.com>",
            'subject' => 'Handboek',
            'from_name' => 'Tom Bakker',
            'from_email' => 'tom@haarlem.nl',
            'to' => [['name' => null, 'email' => 'kennis@noordkade.nl']],
            'content_text' => "Hierbij het bestand {$id}, voor de kennisbank.",
            'attachments' => [['filename' => $filename, 'mime_type' => $mime, 'size' => $size, 'attachment_id' => "att-{$id}", 'part_id' => '1', 'content_id' => null, 'is_inline' => false]],
            'has_attachments' => true,
        ]));

        Tenancy::for($this->account, fn () => InterpretEmail::dispatchSync($email->id));

        return $email;
    }

    private function fakePoppler(string $xml): void
    {
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result($xml),
        ]);
    }
}
