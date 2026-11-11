<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Locale;
use App\Enums\SenderAccess;
use App\Http\Controllers\Controller;
use App\Mail\Previews\MailPreviews;
use App\Mail\RenderedMailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every mail Howy sends, and the ones parked for later, rendered with sample
 * data in each language and for each kind of access, with a test send to
 * yourself to check it in a real mail client.
 */
class MailPreviewController extends Controller
{
    public function index(Request $request, MailPreviews $previews): Response
    {
        $choice = $this->choice($request, $previews);
        $rendered = $previews->render($choice['mail'], $choice['locale'], SenderAccess::from($choice['access']));

        return Inertia::render('admin/mails/index', [
            'mails' => $previews->list(),
            'selected' => $choice,
            'preview' => $rendered === null ? null : [
                'subject' => $rendered->subject,
                'html' => $rendered->html,
                'text' => $rendered->text,
            ],
            'locales' => Options::locales(),
        ]);
    }

    public function send(Request $request, MailPreviews $previews): RedirectResponse
    {
        $choice = $this->choice($request, $previews);
        $rendered = $previews->render($choice['mail'], $choice['locale'], SenderAccess::from($choice['access'])) ?? abort(404);
        $email = (string) $request->user()?->email;

        Mail::to($email)->send(new RenderedMailable($rendered));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test sent to :email.', ['email' => $email])]);

        return back();
    }

    /**
     * @return array{mail: string, locale: string, access: string}
     */
    private function choice(Request $request, MailPreviews $previews): array
    {
        $keys = array_column($previews->list(), 'key');

        $valid = $request->validate([
            'mail' => ['nullable', 'string', Rule::in($keys)],
            'locale' => ['nullable', Rule::enum(Locale::class)],
            'access' => ['nullable', Rule::in([SenderAccess::LearnAndAnswer->value, SenderAccess::Learn->value, SenderAccess::Answer->value])],
        ]);

        return [
            'mail' => (string) ($valid['mail'] ?? $keys[0]),
            'locale' => (string) ($valid['locale'] ?? app()->getLocale()),
            'access' => (string) ($valid['access'] ?? SenderAccess::LearnAndAnswer->value),
        ];
    }
}
