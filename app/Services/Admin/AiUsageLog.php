<?php

namespace App\Services\Admin;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AiUsageRecord;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\KnowledgeTopic;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use stdClass;

/**
 * The platform's AI call log, across every account, for super-admins.
 *
 * TENANCY EXCEPTION: the second request path allowed to call withoutTenancy(),
 * and only through across() below. Seeing where AI money goes across the
 * platform means reading every account's usage records at once. Everything
 * here only reads; each result is mapped to plain arrays inside the bypass,
 * so nothing lazy-loads after it ends; and a single call's payload is loaded
 * back inside Tenancy::for() of its own account. It is reached only from
 * /admin routes behind the super-admin middleware. See CLAUDE.md rule 5 and
 * WithoutTenancyGuardTest.
 */
class AiUsageLog
{
    /** Group-by dimensions the breakdowns support. */
    public const array DIMENSIONS = ['account', 'step', 'model', 'agent', 'subject_type', 'thread', 'subject'];

    /**
     * Calls, newest first.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function calls(AiUsageFilters $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->across(function () use ($filters, $perPage): LengthAwarePaginator {
            $page = $this->filtered($filters)
                ->with(['account:id,name', 'subject' => $this->subjectColumns(...)])
                ->withExists('payload')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();

            /** @var LengthAwarePaginator<int, array<string, mixed>> $mapped */
            $mapped = $page->through(fn (AiUsageRecord $record): array => $this->row($record));

            return $mapped;
        });
    }

    /**
     * @return array{calls: int, failed: int, input_tokens: int, cached_input_tokens: int, output_tokens: int, cost_micros: int, avg_latency_ms: int|null}
     */
    public function totals(AiUsageFilters $filters): array
    {
        return $this->across(function () use ($filters): array {
            $row = $this->filtered($filters)
                ->toBase()
                ->selectRaw('COUNT(*) as calls')
                ->selectRaw('COUNT(*) FILTER (WHERE status = ?) as failed', [AiUsageRecord::FAILED])
                ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
                ->selectRaw('COALESCE(SUM(cached_input_tokens), 0) as cached_input_tokens')
                ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
                ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
                ->selectRaw('AVG(latency_ms) as avg_latency_ms')
                ->first();

            return [
                'calls' => (int) ($row->calls ?? 0),
                'failed' => (int) ($row->failed ?? 0),
                'input_tokens' => (int) ($row->input_tokens ?? 0),
                'cached_input_tokens' => (int) ($row->cached_input_tokens ?? 0),
                'output_tokens' => (int) ($row->output_tokens ?? 0),
                'cost_micros' => (int) ($row->cost_micros ?? 0),
                'avg_latency_ms' => isset($row->avg_latency_ms) ? (int) round((float) $row->avg_latency_ms) : null,
            ];
        });
    }

    /**
     * Calls, tokens and cost grouped by one dimension, most expensive first.
     *
     * @param  value-of<self::DIMENSIONS>  $by
     * @return list<array{key: string, label: string|null, filter: array<string, string|int>, calls: int, failed: int, tokens: int, cost_micros: int}>
     */
    public function breakdown(AiUsageFilters $filters, string $by, ?int $limit = 10): array
    {
        $columns = match ($by) {
            'account' => ['account_id'],
            'thread' => ['account_id', 'thread_key'],
            'subject' => ['account_id', 'subject_type', 'subject_id'],
            default => [$by],
        };

        return $this->across(function () use ($filters, $by, $columns, $limit): array {
            $rows = $this->filtered($filters)
                ->toBase()
                ->select($columns)
                ->selectRaw('COUNT(*) as calls')
                ->selectRaw('COUNT(*) FILTER (WHERE status = ?) as failed', [AiUsageRecord::FAILED])
                ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as tokens')
                ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
                ->when($by === 'thread', fn ($query) => $query->whereNotNull('thread_key'))
                ->when($by === 'subject', fn ($query) => $query->whereNotNull('subject_id'))
                ->groupBy($columns)
                ->orderByDesc('cost_micros')
                ->orderByDesc('calls')
                ->when($limit !== null, fn ($query) => $query->limit((int) $limit))
                ->get();

            $labels = $this->labels($by, $rows->all());

            return array_values($rows->map(function (stdClass $row) use ($by, $labels): array {
                /** @var array<string, mixed> $values */
                $values = (array) $row;
                [$key, $filter] = $this->keyAndFilter($by, $values);

                return [
                    'key' => $key,
                    // Null when there is nothing better than the key: the page words it.
                    'label' => array_key_exists($key, $labels) ? $labels[$key] : ($key === '' ? null : $key),
                    'filter' => $filter,
                    'calls' => (int) $values['calls'],
                    'failed' => (int) $values['failed'],
                    'tokens' => (int) $values['tokens'],
                    'cost_micros' => (int) $values['cost_micros'],
                ];
            })->all());
        });
    }

    /**
     * Calls and cost per day (UTC), every day in the range present.
     *
     * @return list<array{date: string, calls: int, cost_micros: int}>
     */
    public function daily(AiUsageFilters $filters): array
    {
        return $this->across(function () use ($filters): array {
            $rows = $this->filtered($filters)
                ->toBase()
                ->selectRaw("to_char(date_trunc('day', created_at), 'YYYY-MM-DD') as day")
                ->selectRaw('COUNT(*) as calls')
                ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $days = [];

            for ($day = $filters->from->startOfDay(); $day->lessThanOrEqualTo($filters->to); $day = $day->addDay()) {
                $row = $rows->get($day->toDateString());

                $days[] = [
                    'date' => $day->toDateString(),
                    'calls' => (int) ($row->calls ?? 0),
                    'cost_micros' => (int) ($row->cost_micros ?? 0),
                ];
            }

            return $days;
        });
    }

    /**
     * The values the filter selects offer.
     *
     * @return array{accounts: list<array{id: int, name: string}>, steps: list<string>, models: list<string>, agents: list<string>, subject_types: list<string>}
     */
    public function options(): array
    {
        $distinct = fn (string $column): array => $this->across(fn (): array => array_values(AiUsageRecord::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->all()));

        return [
            'accounts' => array_values(Account::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name])
                ->all()),
            'steps' => $distinct('step'),
            'models' => $distinct('model'),
            'agents' => $distinct('agent'),
            'subject_types' => $distinct('subject_type'),
        ];
    }

    /**
     * One call with what was sent and received, if that is still kept.
     *
     * Only the owning account is read across accounts; the record itself is
     * loaded back through the normal scope of that account.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        /** @var array{id: int, account_id: int}|null $target */
        $target = $this->across(fn (): ?array => AiUsageRecord::query()
            ->whereKey($id)
            ->first(['id', 'account_id'])
            ?->only(['id', 'account_id']));

        if ($target === null) {
            return null;
        }

        return Tenancy::for($target['account_id'], function () use ($target): array {
            $record = AiUsageRecord::query()
                ->with(['account:id,name', 'payload', 'subject' => $this->subjectColumns(...)])
                ->withExists('payload')
                ->findOrFail($target['id']);

            return [
                ...$this->row($record),
                'payload' => $record->payload === null ? null : [
                    'instructions' => $record->payload->instructions,
                    'input' => $record->payload->input,
                    'output' => $record->payload->output,
                    'attachments' => $record->payload->attachments ?? [],
                ],
            ];
        });
    }

    /**
     * The one place this class switches tenancy off.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function across(Closure $callback): mixed
    {
        return Tenancy::withoutTenancy($callback);
    }

    /**
     * @return Builder<AiUsageRecord>
     */
    private function filtered(AiUsageFilters $filters): Builder
    {
        return AiUsageRecord::query()
            ->whereBetween('created_at', [$filters->from, $filters->to])
            ->when($filters->accountId, fn (Builder $query, int $id) => $query->where('account_id', $id))
            ->when($filters->step, fn (Builder $query, string $step) => $query->where('step', $step))
            ->when($filters->model, fn (Builder $query, string $model) => $query->where('model', $model))
            ->when($filters->agent, fn (Builder $query, string $agent) => $query->where('agent', $agent))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->subjectType, fn (Builder $query, string $type) => $query->where('subject_type', $type))
            ->when($filters->subjectId, fn (Builder $query, int $id) => $query->where('subject_id', $id))
            ->when($filters->threadKey, fn (Builder $query, string $thread) => $query->where('thread_key', $thread))
            ->when($filters->batch !== null, fn (Builder $query) => $query->where('is_batch', $filters->batch));
    }

    /**
     * Only what a label needs, so a page of calls does not pull in mail bodies.
     *
     * @param  Relation<*, *, *>  $relation
     */
    private function subjectColumns(Relation $relation): void
    {
        if (! $relation instanceof MorphTo) {
            return;
        }

        $relation->constrain([
            Email::class => fn ($query) => $query->select(['id', 'account_id', 'subject', 'provider_thread_id']),
            DocumentVersion::class => fn ($query) => $query->select(['id', 'account_id', 'document_id', 'version_number'])->with('document:id,title'),
            KnowledgeTopic::class => fn ($query) => $query->select(['id', 'account_id', 'name']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AiUsageRecord $record): array
    {
        return [
            'id' => $record->id,
            'created_at' => $record->created_at?->toIso8601String(),
            'account' => ['id' => $record->account_id, 'name' => $record->account->name],
            'step' => $record->step,
            'agent' => $record->agent,
            'provider' => $record->provider,
            'model' => $record->model,
            'status' => $record->status,
            'error' => $record->error,
            'latency_ms' => $record->latency_ms,
            'input_count' => $record->input_count,
            'input_tokens' => $record->input_tokens,
            'cached_input_tokens' => $record->cached_input_tokens,
            'output_tokens' => $record->output_tokens,
            'cost_micros' => $record->estimated_cost_micros,
            'is_batch' => $record->is_batch,
            'subject_type' => $record->subject_type,
            'subject_id' => $record->subject_id,
            'subject_label' => $this->subjectLabel($record->subject),
            'thread_key' => $record->thread_key,
            'has_payload' => (bool) $record->getAttribute('payload_exists'),
        ];
    }

    private function subjectLabel(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Email => $subject->subject,
            $subject instanceof DocumentVersion => trim(($subject->document->title ?? '').' v'.$subject->version_number),
            $subject instanceof KnowledgeTopic => $subject->name,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{0: string, 1: array<string, string|int>}
     */
    private function keyAndFilter(string $by, array $values): array
    {
        return match ($by) {
            'account' => [(string) $values['account_id'], ['account' => (int) $values['account_id']]],
            'thread' => [
                $values['account_id'].':'.$values['thread_key'],
                ['account' => (int) $values['account_id'], 'thread' => (string) $values['thread_key']],
            ],
            'subject' => [
                $values['subject_type'].':'.$values['subject_id'],
                ['account' => (int) $values['account_id'], 'subject_type' => (string) $values['subject_type'], 'subject_id' => (int) $values['subject_id']],
            ],
            // step, model, agent and subject_type filter under their own name.
            default => [(string) ($values[$by] ?? ''), ($values[$by] ?? null) === null ? [] : [$by => (string) $values[$by]]],
        };
    }

    /**
     * Human names for the grouped keys: account names, a thread's mail
     * subject, a subject's title. Runs inside the bypass.
     *
     * @param  array<int, stdClass>  $rows
     * @return array<array-key, string|null>
     */
    private function labels(string $by, array $rows): array
    {
        if ($by === 'account') {
            return Account::query()
                ->whereKey(array_map(fn (stdClass $row): int => (int) $row->account_id, $rows))
                ->pluck('name', 'id')
                ->all();
        }

        if ($by === 'thread') {
            $labels = [];

            foreach ($rows as $row) {
                $subject = Email::query()
                    ->where('account_id', $row->account_id)
                    ->where('provider_thread_id', $row->thread_key)
                    ->oldest('id')
                    ->value('subject');

                $labels[$row->account_id.':'.$row->thread_key] = is_string($subject) ? $subject : null;
            }

            return $labels;
        }

        if ($by === 'subject') {
            $labels = [];

            foreach (collect($rows)->groupBy('subject_type') as $type => $group) {
                $class = Relation::getMorphedModel((string) $type);

                if ($class === null) {
                    continue;
                }

                /** @var class-string<Model> $class */
                $query = $class::query()->whereKey($group->pluck('subject_id')->all());

                if ($class === DocumentVersion::class) {
                    $query->with('document:id,title');
                }

                foreach ($query->get() as $subject) {
                    $labels[$type.':'.$subject->getKey()] = $this->subjectLabel($subject);
                }
            }

            return $labels;
        }

        return [];
    }
}
