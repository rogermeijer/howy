<?php

namespace App\Http\Controllers\Mailboxes;

use App\Enums\EmailSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mailboxes\MailboxSendPolicyRequest;
use App\Jobs\SyncGmailMailbox;
use App\Models\Mailbox;
use App\Services\Gmail\MailboxConnector;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MailboxController extends Controller
{
    public function sync(Mailbox $mailbox): RedirectResponse
    {
        abort_unless($mailbox->isActive(), 409);

        SyncGmailMailbox::dispatch($mailbox->id, EmailSource::Poll);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Checking for new mail.')]);

        return back();
    }

    /**
     * Who Howy may reply to from this mailbox.
     */
    public function updateSendPolicy(MailboxSendPolicyRequest $request, Mailbox $mailbox): RedirectResponse
    {
        $mailbox->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply settings saved.')]);

        return back();
    }

    public function destroy(Mailbox $mailbox, MailboxConnector $connector): RedirectResponse
    {
        $connector->disconnect($mailbox);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':address is disconnected. Its emails are kept.', ['address' => $mailbox->email_address]),
        ]);

        return back();
    }
}
