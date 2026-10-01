<?php

namespace App\Services\Knowledge\Ai\Batch;

final readonly class BatchResult
{
    /**
     * @param  array<string, mixed>|null  $structured  null when the request failed
     */
    public function __construct(
        public string $customId,
        public ?array $structured,
        public string $model,
        public int $inputTokens = 0,
        public int $cachedInputTokens = 0,
        public int $outputTokens = 0,
        public ?string $error = null,
    ) {}
}
