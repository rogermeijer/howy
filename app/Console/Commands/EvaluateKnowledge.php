<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Services\Knowledge\Evaluation\KnowledgeEvaluator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Runs an evaluation set against an account's knowledge base and stores the
 * report, so a change (chunk size, context lines, model, threshold) can be
 * judged by numbers instead of impressions.
 */
#[Signature('knowledge:eval {account : Account id} {--set=database/eval/kennisbank.json : Questions with expected sources} {--k=5 : Passages that count for a hit} {--tokens= : Token budget per question} {--label= : Name for the stored report}')]
#[Description('Measure knowledge base retrieval against a set of questions with known sources')]
class EvaluateKnowledge extends Command
{
    public function handle(KnowledgeEvaluator $evaluator): int
    {
        $account = Account::query()->findOrFail((int) $this->argument('account'));
        $path = base_path((string) $this->option('set'));

        if (! is_file($path)) {
            $this->components->error("No evaluation set at {$path}.");

            return self::FAILURE;
        }

        /** @var array{name?: string, questions: list<array{id: string, category: string, question: string, expected: list<array{document: string, section: string}>, expected_fact?: string|null}>} $set */
        $set = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $report = Tenancy::for($account, fn () => $evaluator->run(
            $set['questions'],
            (int) $this->option('k'),
            $this->option('tokens') ? (int) $this->option('tokens') : null,
        ));

        $rows = [['all', ...array_values($report['summary'])]];

        foreach ($report['by_category'] as $category => $row) {
            $rows[] = [$category, ...array_values($row)];
        }

        $this->table(['', 'questions', 'hit@'.$this->option('k'), 'MRR', 'recall', 'facts', 'tokens', 'p50 ms', 'p95 ms'], $rows);

        foreach ($report['questions'] as $outcome) {
            if (! $outcome['hit']) {
                $this->line("  <fg=red>✗</> [{$outcome['category']}] {$outcome['question']} <fg=gray>→ ".($outcome['top'][0] ?? 'nothing').'</>');
            }
        }

        $file = 'eval/'.now()->format('Ymd-His').'-'.($this->option('label') ?: 'run').'.json';
        Storage::disk('local')->put($file, (string) json_encode([
            'set' => $set['name'] ?? basename($path),
            'account' => $account->id,
            'config' => [
                'k' => (int) $this->option('k'),
                'embedding_model' => config('knowledge.embeddings.model'),
                'min_similarity' => config('knowledge.search.min_similarity'),
                'max_tokens' => $this->option('tokens') ?: config('knowledge.search.max_tokens'),
                'chunking' => config('knowledge.chunking'),
                'models' => config('knowledge.llm.models'),
            ],
            ...$report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->components->info("Report stored in storage/app/private/{$file}");

        return self::SUCCESS;
    }
}
