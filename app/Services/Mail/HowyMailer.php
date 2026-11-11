<?php

namespace App\Services\Mail;

use App\Enums\FingerprintUse;
use App\Enums\MailboxStatus;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\OutboundSkipReason;
use App\Enums\ReplyStatus;
use App\Enums\SenderAccess;
use App\Exceptions\MailboxNeedsReauthException;
use App\Mail\Content\RenderedMail;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Services\Gmail\GmailClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The one place Howy sends mail from a mailbox. Every mail is logged first,
 * as an OutboundMail, and then sent through Gmail with a signed Message-ID,
 * so a reply to it is recognised as a reply to Howy.
 *
 * A mail that may not go is skipped, with why: a colleague who unsubscribed,
 * whose Howy mails are off or whom the rules ignore; a recipient the rules
 * do not let Howy write to. A mailbox that needs reconnecting holds every mail
 * as pending, and adoption:send sends it once the mailbox is back: there is no
 * other sender to fall back on. A disconnected mailbox sends nothing again.
 * Howy's own mails also wait while the account is not live.
 */
class HowyMailer
{
    public function __construct(
        private readonly OutboundMime $mime,
        private readonly MailReferences $references,
        private readonly MailFingerprints $fingerprints,
    ) {}

    /**
     * One of Howy's own mails to a colleague, from their home mailbox, as a
     * message of its own: logged and sent.
     */
    public function toColleague(Colleague $colleague, OutboundMailKind $kind, RenderedMail $mail, ?Email $about = null, ?string $unsubscribeUrl = null): OutboundMail
    {
        return $this->deliver($this->logForColleague($colleague, $kind, $mail, $about, $unsubscribeUrl));
    }

    /**
     * One of Howy's own mails to a colleague, logged as not sent yet, for
     * deliver() to send later (DeliverOutboundMail): what is underway shows
     * at once.
     */
    public function logForColleague(Colleague $colleague, OutboundMailKind $kind, RenderedMail $mail, ?Email $about = null, ?string $unsubscribeUrl = null): OutboundMail
    {
        return OutboundMail::query()->create([
            'mailbox_id' => $colleague->home_mailbox_id,
            'colleague_id' => $colleague->id,
            'email_id' => $about?->id,
            'kind' => $kind,
            'status' => OutboundMailStatus::Pending,
            'recipient' => $colleague->email,
            'subject' => $mail->subject,
            'text' => $mail->text,
            'html' => $mail->html,
            'headers' => array_filter([
                'Auto-Submitted' => 'auto-generated',
                'List-Unsubscribe' => $unsubscribeUrl !== null ? "<{$unsubscribeUrl}>" : null,
                'List-Unsubscribe-Post' => $unsubscribeUrl !== null ? 'List-Unsubscribe=One-Click' : null,
            ]),
        ]);
    }

    /**
     * A reply in the thread of a mail Howy read, to one recipient with others
     * copied in. Whom the rules let Howy write to is the caller's to choose;
     * a recipient they do not allow is skipped here all the same.
     *
     * @param  list<string>  $cc
     */
    public function reply(Email $email, OutboundMailKind $kind, string $recipient, array $cc, RenderedMail $mail): OutboundMail
    {
        $subject = trim((string) $email->subject);
        $headers = ['Auto-Submitted' => 'auto-replied'];

        if ($email->message_id_header !== null) {
            $headers['In-Reply-To'] = $email->message_id_header;
            $headers['References'] = trim(($email->header('References') ?? '').' '.$email->message_id_header);
        }

        $outbound = OutboundMail::query()->create([
            'mailbox_id' => $email->mailbox_id,
            'colleague_id' => $email->colleague_id,
            'email_id' => $email->id,
            'kind' => $kind,
            'status' => OutboundMailStatus::Pending,
            'recipient' => strtolower($recipient),
            'cc' => $cc !== [] ? $cc : null,
            'subject' => preg_match('/^re:/i', $subject) === 1 ? $subject : trim('Re: '.$subject),
            'text' => $mail->text,
            'html' => $mail->html,
            'headers' => $headers,
            'provider_thread_id' => $email->provider_thread_id,
        ]);

        return $this->deliver($outbound);
    }

    /**
     * Send a logged mail that has not gone yet, if it may go now. One sender
     * at a time: the hourly adoption:send and a job may reach the same mail
     * at once, and whoever does not get the lock leaves it to the other.
     */
    public function deliver(OutboundMail $outbound): OutboundMail
    {
        $lock = Cache::lock("outbound-mail:{$outbound->id}", 120);

        if (! $lock->get()) {
            return $outbound;
        }

        try {
            return $this->send($outbound->refresh());
        } finally {
            $lock->release();
        }
    }

    private function send(OutboundMail $outbound): OutboundMail
    {
        if ($outbound->status !== OutboundMailStatus::Pending) {
            return $outbound;
        }

        $mailbox = $outbound->mailbox;
        $reason = $this->reasonNotToSend($outbound, $mailbox);

        if ($reason !== null) {
            $outbound->forceFill(['status' => OutboundMailStatus::Skipped, 'skip_reason' => $reason])->save();

            return $outbound;
        }

        // Paused: the mailbox needs reconnecting, or Howy's own mails wait
        // for the account to be live again.
        if ($mailbox === null || ! $mailbox->isActive() || ($outbound->kind->isFlow() && ! $outbound->account->isLive())) {
            return $outbound;
        }

        $outbound->forceFill(['reference' => $outbound->reference ?? $this->references->for($outbound, $mailbox->domain())])->save();
        $client = GmailClient::for($mailbox);

        try {
            $sent = $client->sendMessage($this->mime->compose($outbound, $mailbox->email_address), $outbound->provider_thread_id);
        } catch (MailboxNeedsReauthException) {
            // The mailbox is out until it is reconnected; the mail waits for it.
            return $outbound;
        } catch (RequestException|ConnectionException $exception) {
            $outbound->forceFill(['status' => OutboundMailStatus::Failed, 'error' => Str::limit($exception->getMessage(), 500)])->save();

            return $outbound;
        }

        $outbound->forceFill([
            'status' => OutboundMailStatus::Sent,
            'sent_at' => now(),
            'provider_message_id' => $sent['id'],
            'provider_thread_id' => $sent['threadId'],
            'message_id_header' => $this->messageIdOf($client, $sent['id']) ?? $outbound->reference,
        ])->save();

        $this->afterSent($outbound);

        return $outbound;
    }

    /**
     * Why a mail may not go at all; null when it may, now or once its mailbox
     * is back.
     */
    private function reasonNotToSend(OutboundMail $outbound, ?Mailbox $mailbox): ?OutboundSkipReason
    {
        if ($mailbox === null || $mailbox->status === MailboxStatus::Disconnected) {
            return OutboundSkipReason::NoMailbox;
        }

        if ($outbound->kind->isReply()) {
            return $mailbox->maySendTo($outbound->recipient) ? null : OutboundSkipReason::NotAllowed;
        }

        if (! $outbound->kind->isFlow()) {
            return null;
        }

        $colleague = $outbound->colleague;

        return match (true) {
            $colleague === null || ! $colleague->receives_mail => OutboundSkipReason::MailOff,
            $colleague->opted_out_at !== null => OutboundSkipReason::OptedOut,
            $mailbox->access($colleague->email) === SenderAccess::Ignore => OutboundSkipReason::Ignored,
            default => null,
        };
    }

    /**
     * The Message-ID Gmail sent the mail with: ours, unless Gmail put its own
     * in its place. Asked for, not assumed; unknown, ours is kept.
     */
    private function messageIdOf(GmailClient $client, string $id): ?string
    {
        try {
            return $client->messageIdOf($id);
        } catch (RequestException|ConnectionException|MailboxNeedsReauthException) {
            return null;
        }
    }

    /**
     * What a sent mail settles: a colleague's step in the flow, or a reply
     * that waited for its mailbox. Howy's own words, quoted back under a
     * reply, are not news.
     */
    private function afterSent(OutboundMail $outbound): void
    {
        $column = $outbound->kind->colleagueColumn();

        if ($column !== null && $outbound->colleague !== null && $outbound->colleague->getAttribute($column) === null) {
            $outbound->colleague->forceFill([$column => $outbound->sent_at])->save();
        }

        $email = $outbound->email;

        if ($outbound->kind->isReply() && $email?->interpretation?->reply_status === ReplyStatus::Waiting) {
            $email->interpretation->forceFill([
                'reply_status' => ReplyStatus::Sent,
                'reply_provider_message_id' => $outbound->provider_message_id,
                'replied_at' => $outbound->sent_at,
            ])->save();
        }

        // What Howy wrote back about a mail, the statements it took from it
        // among them, quoted under a later reply is not news.
        if ($email !== null && ($outbound->kind->isReply() || $outbound->kind === OutboundMailKind::FirstSave)) {
            $this->fingerprints->record($email, $outbound->text, FingerprintUse::Reply);
        }
    }
}
