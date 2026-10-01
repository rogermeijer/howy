<?php

namespace App\Enums;

/**
 * Whether cc: may send mail from a mailbox, and to whom.
 *
 * Stored from day one so the gmail.send scope and this setting ship together,
 * but nothing sends yet: every mailbox stays Off until sending is built.
 */
enum SendPolicy: string
{
    case Off = 'off';

    /** Only to recipients on the mailbox's own domain. */
    case Domain = 'domain';

    /** Only to addresses on the mailbox's send_allowlist. */
    case Allowlist = 'allowlist';
}
