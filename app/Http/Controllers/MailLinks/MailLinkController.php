<?php

namespace App\Http\Controllers\MailLinks;

use App\Enums\MailLinkPurpose;
use App\Enums\OutboundMailKind;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Mail\Content\MailRenderer;
use App\Mail\Flow\FlowMails;
use App\Mail\Flow\FlowRecipient;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Services\Mail\HowyMailer;
use App\Services\Mail\MailLink;
use App\Services\Mail\MailLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Traits\Localizable;
use Inertia\Inertia;

/**
 * What every link in a mail shares: a new link sent on request, for one that
 * expired. A new link goes only to whom the old one went, from
 * the mailbox the mail came from, a few times an hour at most.
 */
class MailLinkController extends Controller
{
    use Localizable;
    use RendersMailLinkPages;

    /** New links a link may ask for in an hour. */
    private const int RENEWALS_PER_HOUR = 3;

    public function renew(Request $request, MailLinks $links, HowyMailer $mailer, FlowMails $flow, MailRenderer $renderer): RedirectResponse
    {
        $link = $this->link($request);
        $mailbox = self::mailboxFor($link);
        $key = 'mail-link-renew:'.sha1((string) $request->route('token'));

        abort_unless($link->purpose->expires(), 404);

        if ($mailbox === null || ! $mailbox->isActive()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Howy cannot send mail right now. Try again later.')]);

            return back();
        }

        if (RateLimiter::tooManyAttempts($key, self::RENEWALS_PER_HOUR)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You asked for a new link a few times already. Try again in an hour.')]);

            return back();
        }

        RateLimiter::hit($key, 3600);

        $colleague = Colleague::query()->where('email', $link->recipient)->first();
        $url = $links->url($link->purpose, $link->recordId, $link->recipient, $link->locale);
        $mail = $this->withLocale($link->locale, fn () => $renderer->render($flow->newLink(
            new FlowRecipient($colleague?->name, $mailbox->access($link->recipient), Tenancy::account()->name, $mailbox->email_address),
            $url,
            (int) config('adoption.mail_link_hours'),
        )));

        $mailer->toAddress($mailbox, $link->recipient, OutboundMailKind::Link, $mail, $colleague);

        return back()->with('mail-link.renewed', true);
    }

    /**
     * The mailbox the mail with the link came from: the one a new link goes
     * from too.
     */
    public static function mailboxFor(MailLink $link): ?Mailbox
    {
        return match ($link->purpose) {
            MailLinkPurpose::Rate => EmailInterpretation::query()->with('email.mailbox')->find($link->recordId)?->email?->mailbox,
            MailLinkPurpose::Review => Email::query()->with('mailbox')->find($link->recordId)?->mailbox,
            MailLinkPurpose::Unsubscribe => null,
        };
    }
}
