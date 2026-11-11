<?php

namespace App\Http\Controllers\MailLinks;

use App\Facades\Tenancy;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Mail\MailLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A page opened from a link in one of Howy's mails: in the language of that
 * mail rather than the browser's, with the account it belongs to.
 */
trait RendersMailLinkPages
{
    protected function link(Request $request): MailLink
    {
        $link = $request->attributes->get('mail_link');

        abort_unless($link instanceof MailLink, 404);

        return $link;
    }

    /**
     * The page for a link that no longer works, offering a new one.
     */
    protected function expiredPage(Request $request): Response
    {
        $link = $this->link($request);
        $mailbox = MailLinkController::mailboxFor($link);
        [$local, $domain] = array_pad(explode('@', $link->recipient, 2), 2, '');

        return $this->page($request, 'mail-links/expired', [
            // a•••@noordkade.nl: enough to recognise one's own address by.
            'recipient' => mb_substr($local, 0, 1).'•••@'.$domain,
            'canRenew' => $mailbox !== null && $mailbox->isActive(),
            'renewed' => (bool) $request->session()->get('mail-link.renewed'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function page(Request $request, string $component, array $props = []): Response
    {
        $link = $this->link($request);

        App::setLocale($link->locale);

        return Inertia::render($component, [
            ...$props,
            'token' => (string) $request->route('token'),
            'company' => Tenancy::account()->name,
            // Page props win over the shared ones, which follow the browser.
            'locale' => $link->locale,
            'translations' => HandleInertiaRequests::catalogue($link->locale),
        ]);
    }
}
