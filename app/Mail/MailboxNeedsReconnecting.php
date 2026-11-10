<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Google refused a mailbox's grant, so no new mail comes in until someone
 * connects it again. Sent once to each admin of the account, in their language.
 *
 * Carries the address and account name rather than the mailbox: a queued
 * tenant model would be restored before any account is set, and throw.
 */
class MailboxNeedsReconnecting extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $address,
        public string $accountName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':address is no longer connected to Howy', ['address' => $this->address]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.mailbox-needs-reconnecting',
            with: ['url' => route('settings')],
        );
    }
}
