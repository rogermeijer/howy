<?php

namespace App\Http\Controllers\MailLinks;

use App\Http\Controllers\Controller;
use App\Models\Colleague;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The way out of Howy's own mails: the welcome and what follows. Answers to
 * one's own questions still go out. The link never expires, and a mail
 * client's one-click unsubscribe (RFC 8058) posts here too, without a session:
 * the link is what authorises it.
 */
class UnsubscribeController extends Controller
{
    use RendersMailLinkPages;

    public function show(Request $request): Response
    {
        $colleague = $this->colleague($request);

        return $this->page($request, 'mail-links/unsubscribe', [
            'email' => $colleague->email,
            'unsubscribed' => $colleague->opted_out_at !== null,
        ]);
    }

    /**
     * Unsubscribe, or, with subscribe, come back.
     */
    public function store(Request $request): RedirectResponse|HttpResponse
    {
        $colleague = $this->colleague($request);

        $colleague->forceFill([
            'opted_out_at' => $request->boolean('subscribe') ? null : ($colleague->opted_out_at ?? now()),
        ])->save();

        // A mail client asks for nothing but the outcome.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return back();
    }

    private function colleague(Request $request): Colleague
    {
        $link = $this->link($request);

        return Colleague::query()->whereKey($link->recordId)->where('email', $link->recipient)->firstOrFail();
    }
}
