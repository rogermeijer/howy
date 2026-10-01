<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AiUsageRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * What the AI cost, per account and per step, from ai_usage_records.
 */
#[Signature('knowledge:usage {--account= : Only this account id} {--since= : From this date (YYYY-MM-DD); default: 30 days ago}')]
#[Description('Show AI token usage and estimated cost per account and step')]
class KnowledgeUsage extends Command
{
    public function handle(): int
    {
        $since = $this->option('since') ? CarbonImmutable::parse((string) $this->option('since')) : now()->subDays(30);
        $rows = [];
        $total = 0;

        $accounts = Account::query()->when($this->option('account'), fn ($query, $id) => $query->whereKey((int) $id))->get();

        foreach ($accounts as $account) {
            Tenancy::for($account, function () use ($account, $since, &$rows, &$total): void {
                $usage = AiUsageRecord::query()
                    ->where('created_at', '>=', $since)
                    ->selectRaw('step, model, COUNT(*) as calls, SUM(input_tokens) as input, SUM(cached_input_tokens) as cached, SUM(output_tokens) as output, SUM(estimated_cost_micros) as cost')
                    ->groupBy('step', 'model')
                    ->orderBy('step')
                    ->get();

                foreach ($usage as $row) {
                    $cost = (int) $row->getAttribute('cost');
                    $total += $cost;

                    $rows[] = [
                        $account->name,
                        $row->getAttribute('step'),
                        $row->getAttribute('model'),
                        number_format((int) $row->getAttribute('calls')),
                        number_format((int) $row->getAttribute('input')),
                        number_format((int) $row->getAttribute('cached')),
                        number_format((int) $row->getAttribute('output')),
                        '$'.number_format($cost / 1_000_000, 4),
                    ];
                }
            });
        }

        $this->table(['Account', 'Step', 'Model', 'Calls', 'Input', 'Cached', 'Output', 'Cost'], $rows);
        $this->components->info(sprintf('Since %s: $%s estimated.', $since->toDateString(), number_format($total / 1_000_000, 4)));

        return self::SUCCESS;
    }
}
