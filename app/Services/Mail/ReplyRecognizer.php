<?php

namespace App\Services\Mail;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Services\Mail\Interpretation\EmailEligibility;

/**
 * Whether a mail that came in replies to one of Howy's: then it says which
 * (emails.outbound_mail_id), and what Howy wrote is the context it is read in.
 *
 * Found, first to last: a signed reference of Howy's in In-Reply-To or
 * References (MailReferences); the Message-ID Gmail sent Howy's mail with,
 * should Gmail have put its own in; the thread of one of Howy's own mails,
 * which start a thread of their own. Only within the mailbox the mail came
 * in on, and only within the account: another account's reference does not
 * verify, and its mails are out of sight.
 */
class ReplyRecognizer
{
    public function __construct(
        private readonly MailReferences $references,
        private readonly EmailEligibility $eligibility,
    ) {}

    public function recognise(Email $email, Mailbox $mailbox): ?OutboundMail
    {
        if ($email->outbound_mail_id !== null) {
            return $email->outboundMail;
        }

        if (! $this->eligibility->fromPerson($email, $mailbox)) {
            return null;
        }

        // The message replied to comes first, then the thread from newest back.
        $named = trim(($email->in_reply_to ?? '').' '.implode(' ', array_reverse(preg_split('/\s+/', trim((string) $email->header('References'))) ?: [])));
        $mail = $this->bySignedReference($named, $mailbox) ?? $this->byMessageId($named, $mailbox) ?? $this->byThread($email, $mailbox);

        if ($mail !== null) {
            $email->outboundMail()->associate($mail)->save();
        }

        return $mail;
    }

    private function bySignedReference(string $named, Mailbox $mailbox): ?OutboundMail
    {
        $references = $this->references->namedIn($named, $mailbox->account_id);

        if ($references === []) {
            return null;
        }

        // The reference as it was given out, nonce and all: an id alone could
        // be one handed out again after the database started over.
        $mails = OutboundMail::query()->where('mailbox_id', $mailbox->id)->whereIn('reference', $references)->get()->keyBy('reference');

        foreach ($references as $reference) {
            if ($mails->has($reference)) {
                return $mails->get($reference);
            }
        }

        return null;
    }

    private function byMessageId(string $named, Mailbox $mailbox): ?OutboundMail
    {
        preg_match_all('/<[^<>\s]+>/', $named, $matches);

        foreach ($matches[0] as $id) {
            $mail = OutboundMail::query()->where('mailbox_id', $mailbox->id)->where('message_id_header', $id)->first();

            if ($mail !== null) {
                return $mail;
            }
        }

        return null;
    }

    /**
     * Howy's mails that are not a reply in someone's thread each start a
     * thread of their own, so a mail in that thread answers one of them,
     * whatever its headers say.
     */
    private function byThread(Email $email, Mailbox $mailbox): ?OutboundMail
    {
        if ($email->provider_thread_id === null) {
            return null;
        }

        $own = array_values(array_filter(OutboundMailKind::cases(), fn (OutboundMailKind $kind): bool => ! $kind->isReply()));

        return OutboundMail::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('provider_thread_id', $email->provider_thread_id)
            ->where('status', OutboundMailStatus::Sent)
            ->whereIn('kind', $own)
            ->latest('sent_at')
            ->first();
    }
}
