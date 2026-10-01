<?php

namespace App\Enums;

/**
 * Where a connected mailbox lives. Only Gmail is wired up; the others are shown
 * in the connect dialog as "coming soon" and have no case until they work.
 */
enum MailboxProvider: string
{
    case Gmail = 'gmail';

    public function label(): string
    {
        return match ($this) {
            self::Gmail => 'Gmail',
        };
    }
}
