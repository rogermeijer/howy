<?php

namespace App\Exceptions;

use App\Models\Mailbox;
use RuntimeException;

/**
 * Google refused the mailbox's refresh token: access was revoked, the password
 * changed, or the grant expired. The mailbox has been flagged NeedsReauth and
 * nothing can be fetched until an admin connects it again.
 */
class MailboxNeedsReauthException extends RuntimeException
{
    public static function for(Mailbox $mailbox): self
    {
        return new self("Mailbox [{$mailbox->id}] needs to be connected again.");
    }
}
