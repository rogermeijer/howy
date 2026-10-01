<?php

namespace App\Enums;

/**
 * Where a document version is in the pipeline. The order of the cases is the
 * order of the pipeline; the UI draws its progress bar from it.
 */
enum ProcessingStatus: string
{
    case Queued = 'queued';
    case Extracting = 'extracting';
    case Structuring = 'structuring';
    case Contextualizing = 'contextualizing';
    case Embedding = 'embedding';

    /** Chunks are embedded: the document can be searched. */
    case Searchable = 'searchable';

    /** Summaries, facts and topics are being generated in a batch. */
    case Enriching = 'enriching';

    case Ready = 'ready';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Ready || $this === self::Failed;
    }

    public function isSearchable(): bool
    {
        return in_array($this, [self::Searchable, self::Enriching, self::Ready], true);
    }
}
