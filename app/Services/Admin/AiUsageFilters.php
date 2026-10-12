<?php

namespace App\Services\Admin;

use Carbon\CarbonImmutable;

/**
 * What the admin AI log is narrowed to. account_id here is a filter a
 * super-admin picks to read with, never a value anything is written under.
 */
final readonly class AiUsageFilters
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $accountId = null,
        public ?string $step = null,
        public ?string $model = null,
        public ?string $agent = null,
        public ?string $status = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?string $threadKey = null,
        public ?bool $batch = null,
    ) {}

    /**
     * The last 30 days, optionally for one account.
     */
    public static function lastDays(int $days = 30, ?int $accountId = null): self
    {
        return new self(
            from: CarbonImmutable::now()->subDays($days)->startOfDay(),
            to: CarbonImmutable::now()->endOfDay(),
            accountId: $accountId,
        );
    }

    /**
     * @param  array<string, mixed>  $input  validated by AiUsageIndexRequest
     */
    public static function fromInput(array $input): self
    {
        $default = self::lastDays();
        $string = fn (string $key): ?string => isset($input[$key]) && $input[$key] !== '' ? (string) $input[$key] : null;
        $int = fn (string $key): ?int => isset($input[$key]) && $input[$key] !== '' ? (int) $input[$key] : null;

        return new self(
            from: isset($input['from']) ? CarbonImmutable::parse((string) $input['from'])->startOfDay() : $default->from,
            to: isset($input['to']) ? CarbonImmutable::parse((string) $input['to'])->endOfDay() : $default->to,
            accountId: $int('account'),
            step: $string('step'),
            model: $string('model'),
            agent: $string('agent'),
            status: $string('status'),
            subjectType: $string('subject_type'),
            subjectId: $int('subject_id'),
            threadKey: $string('thread'),
            batch: isset($input['batch']) && $input['batch'] !== '' ? filter_var($input['batch'], FILTER_VALIDATE_BOOLEAN) : null,
        );
    }

    /**
     * The filters as the page sends them back in its query string.
     *
     * @return array<string, string|int|null>
     */
    public function toQuery(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'account' => $this->accountId,
            'step' => $this->step,
            'model' => $this->model,
            'agent' => $this->agent,
            'status' => $this->status,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'thread' => $this->threadKey,
            'batch' => $this->batch === null ? null : ($this->batch ? '1' : '0'),
        ];
    }
}
