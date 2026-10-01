<?php

namespace App\Services\Knowledge\Search;

use App\Enums\FactStatus;

final readonly class FactHit
{
    public function __construct(
        public int $id,
        public string $statement,
        public FactStatus $status,
        public ?string $validFrom,
        public ?int $documentId,
        public ?string $documentTitle,
        public ?int $sectionId,
        public ?string $headingPath,
        public ?int $pageFrom,
        public float $score,
        /** Set when the fact came from a mail rather than a document. */
        public ?int $emailId = null,
        public ?string $emailSubject = null,
    ) {}
}
