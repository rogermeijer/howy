<?php

namespace App\Http\Controllers\Mailboxes;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Jobs\ImportGmailQuery;
use App\Services\Gmail\MailboxConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GmailOAuthController extends Controller
{
    /**
     * Read mail, and send it once sending is switched on. Asked for together so
     * a mailbox never has to be reconnected when sending ships. Never
     * gmail.modify: Howy does not change or delete anything in the mailbox.
     *
     * @var list<string>
     */
    public const array SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.send',
    ];

    private const string READ_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    public function redirect(Request $request): SymfonyRedirect
    {
        $request->session()->put('mailbox_connect', [
            'account_id' => Tenancy::id(),
            'backfill' => $request->query('backfill') === '30d',
        ]);

        return $this->google()
            ->setScopes(self::SCOPES)
            ->with([
                'access_type' => 'offline',
                // Forces a refresh token even when this Google account granted
                // access before.
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ])
            ->redirect();
    }

    public function callback(Request $request, MailboxConnector $connector): RedirectResponse
    {
        /** @var array{account_id: int, backfill: bool}|null $pending */
        $pending = $request->session()->pull('mailbox_connect');

        // The account could have been switched in another tab between leaving for
        // Google and coming back. Never attach a mailbox to the wrong account.
        abort_unless($pending !== null && $pending['account_id'] === Tenancy::id(), 403);

        if ($request->filled('error')) {
            return $this->fail(__('The mailbox was not connected.'));
        }

        try {
            /** @var GoogleUser $google */
            $google = $this->google()->user();
        } catch (Throwable) {
            return $this->fail(__('Google could not confirm the connection. Please try again.'));
        }

        // Google lets people untick individual permissions on the consent screen.
        if (! in_array(self::READ_SCOPE, $google->approvedScopes, true)) {
            return $this->fail(__('Howy needs permission to read mail. Connect again and allow it.'));
        }

        $mailbox = $connector->connect($google, $request->user());

        if ($pending['backfill']) {
            ImportGmailQuery::dispatch($mailbox->id, 'in:inbox newer_than:30d', $request->user()->id);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':address is connected.', ['address' => $mailbox->email_address]),
        ]);

        return to_route('settings');
    }

    private function google(): GoogleProvider
    {
        /** @var GoogleProvider */
        return Socialite::driver('google');
    }

    private function fail(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('settings');
    }
}
