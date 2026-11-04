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

    /**
     * Fully processed and waiting for an administrator: nothing of it is
     * searched until the version is approved.
     */
    case Review = 'review';

    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * An administrator kept it out. Only the row is left, so the same file is
     * recognised when it is sent again and not proposed a second time.
     */
    case Rejected = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::Review, self::Ready, self::Failed, self::Rejected], true);
    }

    public function isSearchable(): bool
    {
        return in_array($this, [self::Searchable, self::Enriching, self::Ready], true);
    }
}
