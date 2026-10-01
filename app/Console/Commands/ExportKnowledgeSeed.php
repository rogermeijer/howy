<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Services\Knowledge\Seeding\KnowledgeSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Snapshot the processed knowledge base of each account into seed data, so
 * `migrate:fresh --seed` brings it back without running the AI again.
 */
#[Signature('knowledge:export-seed {--account=* : Only these account ids} {--path=database/seeders/data/knowledge : Where the snapshot goes}')]
#[Description('Export processed knowledge base data (documents, chunks, embeddings, facts, folders) as seed data')]
class ExportKnowledgeSeed extends Command
{
    public function handle(KnowledgeSnapshot $snapshot): int
    {
        $directory = base_path((string) $this->option('path'));
        @mkdir($directory, 0755, true);

        $ids = array_map('intval', (array) $this->option('account'));
        $accounts = Account::query()->when($ids !== [], fn ($query) => $query->whereKey($ids))->get();

        foreach ($accounts as $account) {
            Tenancy::for($account, function () use ($account, $snapshot, $directory): void {
                if (! Document::query()->exists()) {
                    return;
                }

                $file = Str::slug($account->name) ?: 'account-'.$account->id;
                $counts = $snapshot->export($directory, $file, $account->name);

                $this->components->info(sprintf('%s → %s.json.gz', $account->name, $file));
                $this->components->bulletList(array_map(fn (string $kind, int $count): string => "{$kind}: {$count}", array_keys($counts), $counts));
            });
        }

        return self::SUCCESS;
    }
}
