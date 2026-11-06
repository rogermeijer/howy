<?php

namespace App\Services\Knowledge\Placement;

use App\Models\Document;

/**
 * An existing document a new file seems to be a version of, and why.
 */
final readonly class DocumentMatch
{
    public function __construct(
        public Document $document,
        public float $score,
        public string $reason,
    ) {}
}
