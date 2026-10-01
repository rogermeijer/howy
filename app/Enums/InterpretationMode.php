<?php

namespace App\Enums;

/**
 * The role cc: takes in a mail, set by where the mailbox is on it.
 */
enum InterpretationMode: string
{
    /** The mailbox is in To: cc: is asked, and answers the sender. */
    case Addressed = 'addressed';

    /**
     * The mailbox is only copied: cc: listens. It never writes to the sender;
     * it may suggest an answer to whoever was asked, and it files what the
     * people in the conversation answer.
     */
    case Copied = 'copied';

    public function label(): string
    {
        return match ($this) {
            self::Addressed => __('Addressed to the mailbox'),
            self::Copied => __('Mailbox in CC'),
        };
    }
}
