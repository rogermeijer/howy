<?php

namespace App\Services\Knowledge\Ai\Batch;

use Laravel\Ai\Contracts\Agent;

/**
 * One prompt in a batch. The custom id comes back with its result, which is
 * the only way to match results (they arrive in any order).
 */
final readonly class BatchRequest
{
    public function __construct(
        public string $customId,
        public Agent $agent,
        public string $prompt,
        public string $step,
    ) {}
}
