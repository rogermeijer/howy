<?php

namespace Tests\Feature\Knowledge;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\User;
use App\Services\Knowledge\DocumentUploader;
use App\Services\Knowledge\Evaluation\KnowledgeEvaluator;
use App\Services\Knowledge\Search\Passage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_evaluation_set_runs_and_stores_a_report(): void
    {
        Storage::fake('knowledge');
        Storage::fake('local');
        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result((string) file_get_contents(base_path('tests/Fixtures/Knowledge/personeelshandboek.pdf.xml'))),
        ]);

        $account = Account::factory()->create(['locale' => 'nl']);

        Tenancy::for($account, function () use ($account) {
            $user = User::factory()->withAccount($account)->create();
            app(DocumentUploader::class)->create(
                UploadedFile::fake()->createWithContent('personeelshandboek.pdf', '%PDF-1.4 eval'),
                ['title' => 'Personeelshandboek', 'type' => 'handbook', 'is_core' => true, 'language' => 'nl'],
                $user,
            );
        });

        $this->artisan('knowledge:eval', ['account' => $account->id, '--label' => 'test'])->assertSuccessful();

        $files = Storage::disk('local')->files('eval');
        $this->assertCount(1, $files);

        /** @var array{summary: array{questions: int, hit_rate: float}, by_category: array<string, array{hit_rate: float}>} $report */
        $report = json_decode((string) Storage::disk('local')->get($files[0]), true);

        $this->assertSame(25, $report['summary']['questions']);
        // Text search alone, no embeddings: a floor, not a target.
        $this->assertGreaterThanOrEqual(0.5, $report['summary']['hit_rate']);
        $this->assertSame(1.0, (float) $report['by_category']['none']['hit_rate']);
    }

    public function test_scoring(): void
    {
        $evaluator = app(KnowledgeEvaluator::class);
        $passage = fn (string $path) => new Passage(1, 1, 1, 'Personeelshandboek', 1, $path, 1, 1, 'tekst met 25 vakantiedagen', 0.03, 50, [1], ['vector' => 1, 'text' => 1], true);

        $question = ['id' => 'q', 'category' => 'fact', 'question' => '?', 'expected' => [['document' => 'handboek', 'section' => '2.1 Vakantie']], 'expected_fact' => '25 vakantiedagen'];

        $second = $evaluator->score($question, [$passage('3 Ziekte'), $passage('2 Verlof › 2.1 Vakantiedagen')], [], 100, 5, 5);
        $this->assertTrue($second['hit']);
        $this->assertSame(0.5, $second['reciprocal_rank']);
        $this->assertTrue($second['fact_found']);

        $outsideK = $evaluator->score($question, [$passage('3 Ziekte'), $passage('2 Verlof › 2.1 Vakantiedagen')], [], 100, 5, 1);
        $this->assertFalse($outsideK['hit']);

        $none = ['id' => 'n', 'category' => 'none', 'question' => '?', 'expected' => []];
        $this->assertTrue($evaluator->score($none, [], [], 0, 5, 5)['hit']);
        $this->assertFalse($evaluator->score($none, [$passage('3 Ziekte')], [], 50, 5, 5)['hit']);
    }
}
