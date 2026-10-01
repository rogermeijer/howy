<?php

namespace Database\Seeders;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Services\Knowledge\Seeding\KnowledgeSnapshot;
use Illuminate\Database\Seeder;

/**
 * Restores knowledge base snapshots made with `php artisan knowledge:export-seed`,
 * each into the account with the same name. Skips an account that already has
 * documents, so seeding twice does not duplicate anything.
 *
 * Must run with model events on: they fill account_id and rebuild folder paths.
 */
class KnowledgeSeeder extends Seeder
{
    public function run(KnowledgeSnapshot $snapshot): void
    {
        $files = glob(database_path('seeders/data/knowledge/*.json.gz')) ?: [];

        foreach ($files as $file) {
            /** @var array{account?: string} $head */
            $head = json_decode((string) gzdecode((string) file_get_contents($file)), true);
            $account = Account::query()->where('name', $head['account'] ?? null)->first();

            if ($account === null) {
                $this->command->warn('No account named "'.($head['account'] ?? '?').'" for '.basename($file).'; skipped.');

                continue;
            }

            Tenancy::for($account, function () use ($snapshot, $file, $account): void {
                if (Document::query()->exists()) {
                    $this->command->warn("{$account->name} already has documents; ".basename($file).' skipped.');

                    return;
                }

                $counts = $snapshot->import($file, (string) config('knowledge.disk'));
                $this->command->info("{$account->name}: {$counts['document']} documents, {$counts['knowledge_chunk']} chunks, {$counts['knowledge_fact']} facts, {$counts['knowledge_topic']} folders.");
            });
        }
    }
}
