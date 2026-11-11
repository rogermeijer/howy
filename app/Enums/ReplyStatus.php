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

    /** Not sent yet: the mailbox is not connected. It goes once it is again. */
    case Waiting = 'waiting';

    /** The mailbox's rules do not allow replying to the sender, or replies are off. */
    case Blocked = 'blocked';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::None => __('No reply'),
            self::Sent => __('Reply sent'),
            self::Waiting => __('Reply waits for the mailbox'),
            self::Blocked => __('Reply blocked by the mailbox rules'),
            self::Failed => __('Reply failed'),
        };
    }
}
