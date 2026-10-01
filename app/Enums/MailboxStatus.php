<?php

namespace App\Enums;

enum MailboxStatus: string
{
    /** Tokens are valid and new mail is being received. */
    case Active = 'active';

    /** Google rejected the refresh token; an admin has to connect it again. */
    case NeedsReauth = 'needs_reauth';

    /** Disconnected on purpose. Tokens are wiped, stored emails are kept. */
    case Disconnected = 'disconnected';
}
