<?php

namespace App\Console\Commands;

use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use App\Facades\Tenancy;
use App\Jobs\Knowledge\CollectEnrichmentBatch;
use App\Models\Account;
use App\Models\DocumentProcessingStep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Batches finish on the provider's schedule, not ours: check the running ones
 * and collect what is done.
 */
#[Signature('knowledge:poll-batches')]
#[Description('Collect finished enrichment batches for every account')]
class PollKnowledgeBatches extends Command
{
    public function handle(): int
    {
        $queued = 0;

        Account::query()->each(function (Account $account) use (&$queued): void {
            Tenancy::for($account, function () use (&$queued): void {
                DocumentProcessingStep::query()
                    ->where('step', ProcessingStep::Enrich)
                    ->where('status', StepStatus::Running)
                    ->whereNotNull('provider_batch_id')
                    ->pluck('document_version_id')
                    ->each(function (int $versionId) use (&$queued): void {
                        CollectEnrichmentBatch::dispatch($versionId);
                        $queued++;
                    });
            });
        });

        $this->components->info("Checking {$queued} batches.");

        return self::SUCCESS;
    }
}
