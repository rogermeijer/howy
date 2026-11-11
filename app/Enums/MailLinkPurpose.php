<?php

namespace App\Enums;

/**
 * What a link in one of Howy's mails opens, without logging in.
 */
enum MailLinkPurpose: string
{
    /** Rating an answer Howy gave (05). */
    case Rate = 'rate';

    /** Looking over what Howy took from a mail, and adjusting it (04). */
    case Review = 'review';

    /** Unsubscribing from Howy's own mails. */
    case Unsubscribe = 'unsubscribe';

    /**
     * Whether the link stops working after config('adoption.mail_link_hours').
     * The way out of Howy's mails never does.
     */
    public function expires(): bool
    {
        return $this !== self::Unsubscribe;
    }
}
