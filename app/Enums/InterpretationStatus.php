<?php

namespace App\Enums;

/**
 * Where a mail is in being interpreted.
 */
enum InterpretationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Done = 'done';

    /** Not interpreted on purpose, e.g. no AI provider configured. */
    case Skipped = 'skipped';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Processing => __('Processing'),
            self::Done => __('Processed'),
            self::Skipped => __('Skipped'),
            self::Failed => __('Failed'),
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Done, self::Skipped, self::Failed], true);
    }
}
