<?php

namespace App\Services\Knowledge\Ai\Batch;

final readonly class BatchStatus
{
    public const string PENDING = 'pending';

    public const string COMPLETED = 'completed';

    public const string FAILED = 'failed';

    /**
     * @param  list<BatchResult>  $results  only when completed
     */
    public function __construct(
        public string $state,
        public array $results = [],
        public ?string $error = null,
    ) {}
}
