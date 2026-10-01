<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Jobs\Knowledge\EmbedChunks;
use App\Models\Account;
use App\Models\Document;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Ai\AiGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * After switching embedding model: re-embed everything that was embedded with
 * another one. (Changing the dimensions also needs a migration of the vector
 * columns and their indexes first.)
 */
#[Signature('knowledge:reembed {--account= : Only this account id}')]
#[Description('Re-embed chunks and facts that were embedded with a different model than the configured one')]
class ReembedKnowledge extends Command
{
    public function handle(AiGateway $ai): int
    {
        $model = (string) config('knowledge.embeddings.model');
        $accounts = Account::query()->when($this->option('account'), fn ($query, $id) => $query->whereKey((int) $id))->get();

        foreach ($accounts as $account) {
            Tenancy::for($account, function () use ($ai, $model, $account): void {
                $versions = Document::query()->whereNotNull('current_version_id')->pluck('current_version_id');

                foreach ($versions as $versionId) {
                    EmbedChunks::dispatch((int) $versionId);
                }

                $facts = KnowledgeFact::query()->whereNotNull('embedding')->where('embedding_model', '!=', $model)->get();

                foreach ($facts->chunk(64) as $batch) {
                    $vectors = $ai->embed(array_values($batch->map(fn (KnowledgeFact $fact): string => trim(($fact->subject ? $fact->subject.': ' : '').$fact->statement))->all()), 'embed');

                    foreach (array_values($batch->all()) as $index => $fact) {
                        $fact->update(['embedding' => $vectors[$index], 'embedding_model' => $model]);
                    }
                }

                $this->components->info("{$account->name}: {$versions->count()} documents queued, {$facts->count()} facts re-embedded.");
            });
        }

        return self::SUCCESS;
    }
}
