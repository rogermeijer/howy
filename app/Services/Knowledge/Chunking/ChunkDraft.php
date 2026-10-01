<?php

namespace App\Services\Knowledge\Chunking;

use App\Enums\ChunkKind;

final readonly class ChunkDraft
{
    public function __construct(
        public ChunkKind $kind,
        public string $content,
        public ?int $pageFrom,
        public ?int $pageTo,
        public int $tokenCount,
    ) {}
}
