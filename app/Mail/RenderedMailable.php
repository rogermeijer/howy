<?php

namespace App\Mail;

use App\Mail\Content\RenderedMail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A mail that is already rendered, sent through the app's mailer as it is:
 * the test mail from /admin/mails.
 */
class RenderedMailable extends Mailable
{
    public function __construct(public RenderedMail $mail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mail->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rendered-html',
            text: 'mail.rendered-text',
            with: ['html' => $this->mail->html, 'text' => $this->mail->text],
        );
    }
}
