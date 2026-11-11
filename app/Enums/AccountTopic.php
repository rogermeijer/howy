<?php

namespace App\Enums;

/**
 * What changed in an account, as its open pages hear it on the account's
 * channel: a name for what to reload, never the data itself.
 */
enum AccountTopic: string
{
    /** Mail came in, or Howy read it, or someone rated what Howy answered. */
    case Mail = 'mail';

    /** A mail Howy logged, sent, held back or failed. */
    case SentMails = 'sent-mails';

    /** What waits for an administrator's approval. */
    case Approvals = 'approvals';
}
