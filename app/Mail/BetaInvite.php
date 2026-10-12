<?php

namespace App\Mail;

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
        return new Envelope(
            subject: __('Your invite to Howy'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.beta-invite',
            with: [
                'code' => $this->invite->code,
                'url' => route('register.invite', $this->invite->code),
            ],
        );
    }
}
