<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\ReplyStatus;
use App\Exceptions\MailboxNeedsReauthException;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as MimeEmail;

/**
 * Replies to a mail in its own thread, from the mailbox it came in on: to the
 * sender, or with a suggestion to the people who were asked. Only to whom the
 * mailbox's send policy allows. A blocked or failed reply is
 * kept on the interpretation, so the answer is never lost.
 */
class ThreadReplier
{
    public function __construct(private readonly GmailMessageParser $parser) {}

    /**
     * Answer the sender (Reply-To when set, else From), with everyone else
     * the mail was addressed or copied to in CC, so they see cc: has it.
     */
    public function reply(EmailInterpretation $interpretation, Email $email, ComposedReply $body): void
    {
        $recipient = $this->recipient($email);

        $this->deliver($interpretation, $email, $recipient !== null ? [$recipient] : [], $body, $this->copiedOn($email, $recipient));
    }

    /**
     * Suggest an answer to the people a question was put to, and to no one
     * else: the sender never hears from cc: when the mailbox is only copied.
     */
    public function suggest(EmailInterpretation $interpretation, Email $email, ComposedReply $body): void
    {
        $this->deliver($interpretation, $email, $this->askedOf($email), $body);
    }

    /**
     * The people a mail was written to: To, without the sender and the mailbox.
     *
     * @return list<string>
     */
    public function askedOf(Email $email): array
    {
        $exclude = array_filter([strtolower((string) $email->from_email), strtolower((string) $email->mailbox?->email_address)]);

        return array_values(array_unique(array_filter(
            array_map(fn (array $recipient): string => strtolower($recipient['email']), $email->to ?? []),
            fn (string $address): bool => $address !== '' && ! in_array($address, $exclude, true),
        )));
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
     * Send to every recipient the send policy allows, copying in those of
     * $cc it allows too. With no recipient allowed the reply is kept,
     * blocked, and nobody is copied; a retried job never sends it twice.
     *
     * @param  list<string>  $recipients
     * @param  list<string>  $cc
     */
    private function deliver(EmailInterpretation $interpretation, Email $email, array $recipients, ComposedReply $body, array $cc = []): void
    {
        if ($interpretation->reply_provider_message_id !== null) {
            return;
        }

        $interpretation->reply_text = $body->text;
        $mailbox = $email->mailbox;

        if ($mailbox === null || ! $mailbox->isActive()) {
            $interpretation->reply_recipients = $recipients;
            $this->finish($interpretation, ReplyStatus::Failed, __('The mailbox is no longer connected.'));

            return;
        }

        $allowed = array_values(array_filter($recipients, fn (string $recipient): bool => $mailbox->maySendTo($recipient)));
        $copied = array_values(array_filter($cc, fn (string $address): bool => $mailbox->maySendTo($address)));
        $interpretation->reply_recipients = $allowed !== [] ? [...$allowed, ...$copied] : $recipients;

        if ($allowed === []) {
            $this->finish($interpretation, ReplyStatus::Blocked);

            return;
        }

        try {
            $sent = GmailClient::for($mailbox)->sendMessage($this->compose($email, $mailbox->email_address, $allowed, $copied, $body), $email->provider_thread_id);
        } catch (RequestException|ConnectionException|MailboxNeedsReauthException $exception) {
            $this->finish($interpretation, ReplyStatus::Failed, Str::limit($exception->getMessage(), 500));

            return;
        }

        $interpretation->fill(['reply_provider_message_id' => $sent['id'], 'replied_at' => now()]);
        $this->finish($interpretation, ReplyStatus::Sent);
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

    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    private function compose(Email $email, string $from, array $to, array $cc, ComposedReply $body): string
    {
        $subject = trim((string) $email->subject);

        $message = (new MimeEmail)
            ->from(new Address($from))
            ->to(...array_map(fn (string $address): Address => new Address($address), $to))
            ->subject(preg_match('/^re:/i', $subject) === 1 ? $subject : trim('Re: '.$subject))
            ->text($body->text)
            ->html($body->html);

        if ($cc !== []) {
            $message->cc(...array_map(fn (string $address): Address => new Address($address), $cc));
        }

        // Inline, so the HTML's cid: references show the image in place.
        foreach ($body->inline as $name => $path) {
            $message->embedFromPath($path, $name);
        }

        if ($email->message_id_header !== null) {
            $references = trim(($email->header('References') ?? '').' '.$email->message_id_header);
            $message->getHeaders()->addIdHeader('In-Reply-To', $this->ids($email->message_id_header));
            $message->getHeaders()->addIdHeader('References', $this->ids($references));
        }

        // Tells other systems (and cc: itself) not to answer this automatically.
        $message->getHeaders()->addTextHeader('Auto-Submitted', 'auto-replied');

        return $message->toString();
    }

    /**
     * Message ids without their angle brackets, as Symfony wants them.
     *
     * @return list<string>
     */
    private function ids(string $header): array
    {
        preg_match_all('/<([^>]+)>/', $header, $matches);

        return $matches[1] !== [] ? $matches[1] : [trim($header, '<> ')];
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
