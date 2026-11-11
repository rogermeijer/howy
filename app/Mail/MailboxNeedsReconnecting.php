<?php

namespace App\Mail;

use App\Mail\Content\MailContent;
use App\Mail\Content\MailRenderer;
use App\Mail\Content\MailUrl;
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
        return new Envelope(subject: $this->mail()->subject);
    }

    public function content(): Content
    {
        $rendered = app(MailRenderer::class)->render($this->mail());

        return new Content(
            view: 'mail.rendered-html',
            text: 'mail.rendered-text',
            with: ['html' => $rendered->html, 'text' => $rendered->text],
        );
    }

    private function mail(): MailContent
    {
        return app(SystemMails::class)->mailboxNeedsReconnecting($this->address, $this->accountName, MailUrl::route('settings'));
    }
}
