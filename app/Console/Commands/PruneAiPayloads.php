<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AiCallPayload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Forget what was sent to and received from the AI once it is older than
 * knowledge.ai_log.payload_retention_days. The usage records — tokens, cost,
 * latency, thread — stay, so cost can still be analysed afterwards.
 */
#[Signature('knowledge:prune-ai-payloads')]
#[Description('Delete AI call payloads older than the retention window')]
class PruneAiPayloads extends Command
{
    public function handle(): int
    {
        $before = now()->subDays((int) config('knowledge.ai_log.payload_retention_days'));
        $deleted = 0;

        foreach (Account::query()->get() as $account) {
            $deleted += Tenancy::for($account, fn (): int => AiCallPayload::query()
                ->where('created_at', '<', $before)
                ->delete());
        }

        $this->components->info("Pruned {$deleted} AI call payloads from before {$before->toDateString()}.");

        return self::SUCCESS;
    }
}
