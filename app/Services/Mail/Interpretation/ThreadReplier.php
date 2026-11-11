<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\OutboundSkipReason;
use App\Enums\ReplyStatus;
use App\Mail\Content\RenderedMail;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Services\Gmail\GmailMessageParser;
use App\Services\Mail\HowyMailer;

/**
 * Replies to a mail in its own thread, from the mailbox it came in on, to the
 * sender and only to whom the mailbox's rules allow, through HowyMailer like
 * every mail Howy sends. A blocked, waiting or failed reply is kept on the
 * interpretation, so the answer is never lost.
 */
class ThreadReplier
{
    public function __construct(
        private readonly GmailMessageParser $parser,
        private readonly HowyMailer $mailer,
    ) {}

    /**
     * Answer the sender (Reply-To when set, else From), with everyone else
     * the mail was addressed or copied to in CC, so they see Howy has it.
     */
    public function reply(EmailInterpretation $interpretation, Email $email, ComposedReply $body, OutboundMailKind $kind = OutboundMailKind::Answer): void
    {
        $recipient = $this->recipient($email);

        $this->deliver($interpretation, $email, $kind, $recipient, $body, $this->copiedOn($email, $recipient));
    }

    /**
     * Everyone else on a mail, To and CC, without the sender, the reply's
     * recipient and the mailbox: who a reply to all copies in.
     *
     * @return list<string>
     */
    public function copiedOn(Email $email, ?string $recipient): array
    {
        $exclude = array_filter([
            strtolower((string) $email->from_email),
            strtolower((string) $recipient),
            strtolower((string) $email->mailbox?->email_address),
        ]);

        return array_values(array_unique(array_filter(
            array_map(fn (array $address): string => strtolower($address['email']), [...($email->to ?? []), ...($email->cc ?? [])]),
            fn (string $address): bool => $address !== '' && ! in_array($address, $exclude, true),
        )));
    }

    /**
     * Send to the recipient when the rules allow it, copying in those of $cc
     * they allow too. Not allowed, the reply is kept, blocked, and nobody is
     * copied; a retried job never sends it twice, and a reply that waits for
     * its mailbox goes as it was written.
     *
     * @param  list<string>  $cc
     */
    private function deliver(EmailInterpretation $interpretation, Email $email, OutboundMailKind $kind, ?string $recipient, ComposedReply $body, array $cc = []): void
    {
        if ($interpretation->reply_provider_message_id !== null || $interpretation->reply_status === ReplyStatus::Waiting) {
            return;
        }

        $interpretation->reply_text = $body->text;
        $mailbox = $email->mailbox;

        if ($mailbox === null) {
            $interpretation->reply_recipients = $recipient !== null ? [$recipient] : [];
            $this->finish($interpretation, ReplyStatus::Failed, __('The mailbox is no longer connected.'));

            return;
        }

        $allowed = $recipient !== null && $mailbox->maySendTo($recipient);
        $copied = $allowed ? array_values(array_filter($cc, fn (string $address): bool => $mailbox->maySendTo($address))) : [];
        $interpretation->reply_recipients = array_values(array_filter([$recipient, ...$copied]));

        if ($recipient === null) {
            $this->finish($interpretation, ReplyStatus::Blocked);

            return;
        }

        $outbound = $this->mailer->reply($email, $kind, $recipient, $copied, new RenderedMail('', $body->html, $body->text));

        match ($outbound->status) {
            OutboundMailStatus::Sent => $this->finish($interpretation->fill([
                'reply_provider_message_id' => $outbound->provider_message_id,
                'replied_at' => $outbound->sent_at,
            ]), ReplyStatus::Sent),
            OutboundMailStatus::Pending => $this->finish($interpretation, ReplyStatus::Waiting),
            OutboundMailStatus::Skipped => $outbound->skip_reason === OutboundSkipReason::NoMailbox
                ? $this->finish($interpretation, ReplyStatus::Failed, __('The mailbox is no longer connected.'))
                : $this->finish($interpretation, ReplyStatus::Blocked),
            OutboundMailStatus::Failed => $this->finish($interpretation, ReplyStatus::Failed, $outbound->error),
        };
    }

    /**
     * Who the reply goes to: Reply-To when the sender set one, else From.
     */
    public function recipient(Email $email): ?string
    {
        $replyTo = $email->header('Reply-To');
        $address = $replyTo !== null ? ($this->parser->addresses($replyTo)[0]['email'] ?? null) : null;

        return $address ?? $email->from_email;
    }

    private function finish(EmailInterpretation $interpretation, ReplyStatus $status, ?string $error = null): void
    {
        $interpretation->reply_status = $status;

        if ($error !== null) {
            $interpretation->error = $error;
        }

        $interpretation->save();
    }
}
