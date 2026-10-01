<?php

namespace App\Services\Knowledge\Search;

use App\Enums\DocumentType;
use App\Enums\FactStatus;
use Carbon\CarbonInterface;

final readonly class SearchQuery
{
    /**
     * @param  list<DocumentType>  $documentTypes  empty: all types
     * @param  list<FactStatus>  $factStatuses
     * @param  int|null  $topicId  only content filed in this folder or below
     * @param  string|null  $language  full-text configuration; null: the account's language
     */
    public function __construct(
        public string $text,
        public array $documentTypes = [],
        public ?CarbonInterface $effectiveFrom = null,
        public ?CarbonInterface $effectiveUntil = null,
        public array $factStatuses = [FactStatus::Core, FactStatus::Supplementary],
        public bool $includeFacts = true,
        public ?int $topicId = null,
        public ?int $candidateLimit = null,
        public ?int $maxTokens = null,
        public ?string $language = null,
    ) {}
}
