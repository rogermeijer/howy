<?php

namespace App\Enums;

/**
 * Where a mail Howy sends stands. Every mail is logged as pending before it
 * goes; one that may not go is skipped, with the reason.
 */
enum OutboundMailStatus: string
{
    /**
     * Not sent yet: about to go, or waiting for its mailbox to be connected
     * again, or for the account to be live again.
     */
    case Pending = 'pending';

    case Sent = 'sent';

    case Skipped = 'skipped';

    case Failed = 'failed';

    /**
     * A mail that went, or will: one more of the same kind is not needed.
     *
     * @return list<self>
     */
    public static function underway(): array
    {
        return [self::Pending, self::Sent];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Not sent yet'),
            self::Sent => __('Sent'),
            self::Skipped => __('Not sent'),
            self::Failed => __('Failed'),
        };
    }
}
