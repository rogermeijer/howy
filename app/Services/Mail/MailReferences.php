<?php

namespace App\Services\Mail;

use App\Models\OutboundMail;

/**
 * Howy's own Message-IDs: <howy.{id}.{signature}@{mailbox domain}>. The
 * signature, an HMAC over the account and the mail with the app key, proves
 * Howy gave the id out: a reply naming it is a reply to that mail, and an id
 * made up, or another account's, is not.
 */
class MailReferences
{
    private const string PATTERN = '/<howy\.(\d+)\.([a-f0-9]{16})@[^<>\s]+>/i';

    public function for(OutboundMail $mail, string $domain): string
    {
        return '<howy.'.$mail->id.'.'.$this->signature($mail->account_id, $mail->id).'@'.$domain.'>';
    }

    /**
     * The ids of Howy's mails that these headers name and that verify for
     * this account, in the order named.
     *
     * @return list<int>
     */
    public function idsIn(string $headers, int $accountId): array
    {
        preg_match_all(self::PATTERN, $headers, $matches, PREG_SET_ORDER);

        $ids = [];

        foreach ($matches as [, $id, $signature]) {
            if (hash_equals($this->signature($accountId, (int) $id), strtolower($signature))) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function signature(int $accountId, int $mailId): string
    {
        return substr(hash_hmac('sha256', "outbound-mail:{$accountId}:{$mailId}", (string) config('app.key')), 0, 16);
    }
}
