<?php

namespace Tests\Feature\Knowledge;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Models\User;
use App\Services\Knowledge\DocumentUploader;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\SearchQuery;
use App\Services\Knowledge\Seeding\KnowledgeSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * A processed knowledge base survives export and import into a fresh account
 * unchanged, without any AI call on the way back.
 */
class KnowledgeSnapshotTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/cc-snapshot-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_a_processed_knowledge_base_round_trips_through_a_snapshot(): void
    {
        Storage::fake('knowledge');
        $this->fakeKnowledgeAi();
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);

        $source = Account::factory()->create(['name' => 'Bron']);
        $before = Tenancy::for($source, function () use ($source) {
            $user = User::factory()->withAccount($source)->create();
            app(DocumentUploader::class)->create(UploadedFile::fake()->createWithContent('handboek.pdf', '%PDF-1.4 snapshot'), ['type' => 'handbook', 'is_core' => true, 'language' => 'nl'], $user);

            app(KnowledgeSnapshot::class)->export($this->directory, 'bron', 'Bron');

            return $this->fingerprint();
        });

        $this->assertFileExists("{$this->directory}/bron.json.gz");
        $this->assertGreaterThan(0, $before['facts']);
        $this->assertGreaterThan(0, $before['topics']);

        // A different account, a fresh start: no AI may be called.
        config(['ai.providers.openai.key' => null]);
        $target = Account::factory()->create(['name' => 'Doel']);

        Tenancy::for($target, function () use ($before) {
            app(KnowledgeSnapshot::class)->import("{$this->directory}/bron.json.gz", 'knowledge');

            $this->assertSame($before, $this->fingerprint());

            // Folder paths were rebuilt from the new ids.
            foreach (KnowledgeTopic::query()->whereNotNull('parent_id')->with('parent')->get() as $topic) {
                $this->assertStringStartsWith($topic->parent?->path.'.', $topic->path);
            }

            $document = Document::query()->with('currentVersion')->sole();
            $this->assertNotNull($document->currentVersion);
            Storage::disk('knowledge')->assertExists($document->currentVersion->path);
            Storage::disk('knowledge')->assertExists((string) $document->currentVersion->extracted_path);

            $result = app(KnowledgeSearch::class)->search(new SearchQuery('ziekmelding WhatsApp'));
            $this->assertNotEmpty($result->passages);
            $this->assertSame($document->id, $result->passages[0]->documentId);
        });

        // The source account is untouched.
        Tenancy::for($source, fn () => $this->assertSame($before, $this->fingerprint()));
    }

    /**
     * @return array<string, mixed>
     */
    private function fingerprint(): array
    {
        return [
            'sections' => DocumentSection::query()->count(),
            'chunks' => KnowledgeChunk::query()->orderBy('content_hash')->get()->map(fn (KnowledgeChunk $chunk) => [$chunk->content_hash, $chunk->context, $chunk->getRawOriginal('embedding'), $chunk->is_current])->all(),
            'facts' => KnowledgeFact::query()->orderBy('content_hash')->get()->map(fn (KnowledgeFact $fact) => [$fact->statement, $fact->status->value, $fact->section?->heading_path])->all(),
            'topics' => KnowledgeTopic::query()->with('parent')->get()->map(fn (KnowledgeTopic $topic) => ($topic->parent->name ?? '').'>'.$topic->name.'@'.$topic->depth)->sort()->values()->all(),
            'links' => KnowledgeTopicLink::query()->with(['topic', 'linkable'])->get()->map(fn (KnowledgeTopicLink $link) => $link->topic->name.'='.($link->linkable instanceof DocumentSection ? $link->linkable->heading_path : '?'))->sort()->values()->all(),
        ];
    }
}
