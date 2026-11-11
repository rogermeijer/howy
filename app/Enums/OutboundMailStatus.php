<?php

namespace App\Enums;

/**
 * Where a mail Howy sends stands. Every mail is logged as pending before it
 * goes; one that may not go is skipped, with the reason.
 */
enum OutboundMailStatus: string
{
    /** Not sent yet: waiting for its mailbox to be connected again. */
    case Pending = 'pending';

    case Sent = 'sent';

    case Skipped = 'skipped';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for the mailbox'),
            self::Sent => __('Sent'),
            self::Skipped => __('Not sent'),
            self::Failed => __('Failed'),
        };
    }
}
