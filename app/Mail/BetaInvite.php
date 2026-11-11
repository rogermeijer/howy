<?php

namespace App\Mail;

use App\Mail\Content\MailContent;
use App\Mail\Content\MailRenderer;
use App\Models\BetaAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The private beta invite: the code, and a link that fills it in. Sent from
 * /admin/beta, in the language the request was made in.
 */
class BetaInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public BetaAccessRequest $invite) {}

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
        return app(SystemMails::class)->betaInvite($this->invite->code, route('register.invite', $this->invite->code));
    }
}
