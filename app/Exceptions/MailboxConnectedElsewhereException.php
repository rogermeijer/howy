<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The Gmail address is already active in another account. An address is active
 * in one account at most, because disconnecting it there revokes the grant at
 * Google and would unlink it here too.
 */
class MailboxConnectedElsewhereException extends RuntimeException
{
    public static function for(string $address): self
    {
        return new self("Mailbox [{$address}] is already active in another account.");
    }
}
