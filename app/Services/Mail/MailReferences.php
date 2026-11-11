<?php

namespace App\Services\Mail;

use App\Models\OutboundMail;
use Illuminate\Support\Str;

/**
 * Howy's own Message-IDs: <howy.{id}.{nonce}.{signature}@{mailbox domain}>.
 *
 * The nonce makes each one unique for good. Without it, a database started
 * over under the same app key would hand out an id it gave out before, and
 * Gmail silently drops a mail whose Message-ID the recipient already has: it
 * shows as sent and never arrives. The signature, an HMAC over the account,
 * the mail and the nonce with the app key, proves Howy gave the id out: a
 * reply naming it is a reply to that mail, and an id made up, or another
 * account's, is not.
 */
class MailReferences
{
    private const string PATTERN = '/<howy\.(\d+)\.([a-z0-9]{10})\.([a-f0-9]{16})@[^<>\s]+>/i';

    public function for(OutboundMail $mail, string $domain): string
    {
        $nonce = Str::lower(Str::random(10));

        return '<howy.'.$mail->id.'.'.$nonce.'.'.$this->signature($mail->account_id, $mail->id, $nonce).'@'.$domain.'>';
    }

    /**
     * The references of Howy's that these headers name and that verify for
     * this account, in the order named.
     *
     * @return list<string>
     */
    public function namedIn(string $headers, int $accountId): array
    {
        preg_match_all(self::PATTERN, $headers, $matches, PREG_SET_ORDER);

        $references = [];

        foreach ($matches as [$reference, $id, $nonce, $signature]) {
            if (hash_equals($this->signature($accountId, (int) $id, strtolower($nonce)), strtolower($signature))) {
                $references[] = $reference;
            }
        }

        return array_values(array_unique($references));
    }

    private function signature(int $accountId, int $mailId, string $nonce): string
    {
        return substr(hash_hmac('sha256', "outbound-mail:{$accountId}:{$mailId}:{$nonce}", (string) config('app.key')), 0, 16);
    }
}
