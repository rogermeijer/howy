<?php

namespace App\Enums;

/**
 * Why Howy did not send a mail it logged.
 */
enum OutboundSkipReason: string
{
    /** They unsubscribed from Howy's own mails. */
    case OptedOut = 'opted_out';

    /** An admin switched their Howy mails off. */
    case MailOff = 'mail_off';

    /** The mailbox's rules ignore them. */
    case Ignored = 'ignored';

    /** The rules do not let Howy write to them. */
    case NotAllowed = 'not_allowed';

    /** There is no mailbox to send it from. */
    case NoMailbox = 'no_mailbox';

    public function label(): string
    {
        return match ($this) {
            self::OptedOut => __('They unsubscribed'),
            self::MailOff => __('Their Howy mails are off'),
            self::Ignored => __('The rules of the mailbox ignore them'),
            self::NotAllowed => __('The rules of the mailbox do not let Howy write to them'),
            self::NoMailbox => __('There is no mailbox to send it from'),
        };
    }
}
