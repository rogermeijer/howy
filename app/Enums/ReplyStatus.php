<?php

namespace App\Enums;

/**
 * Whether the reply to an interpreted mail went out.
 */
enum ReplyStatus: string
{
    /** No reply was needed. */
    case None = 'none';

    case Sent = 'sent';

    /** The mailbox's send policy does not allow replying to the sender. */
    case Blocked = 'blocked';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::None => __('No reply'),
            self::Sent => __('Reply sent'),
            self::Blocked => __('Reply blocked by reply settings'),
            self::Failed => __('Reply failed'),
        };
    }
}
