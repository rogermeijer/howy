<?php

namespace App\Http\Controllers\Mailboxes;

use App\Enums\EmailSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mailboxes\MailboxRulesRequest;
use App\Jobs\SyncGmailMailbox;
use App\Models\Mailbox;
use App\Services\Gmail\MailboxConnector;
use App\Services\Mailboxes\MailboxRuleEditor;
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
     * What Howy may do with each sender's mail on this mailbox: what changed
     * in the rules panel.
     */
    public function updateRules(MailboxRulesRequest $request, Mailbox $mailbox, MailboxRuleEditor $rules): RedirectResponse
    {
        /** @var string $domainAccess */
        $domainAccess = $request->validated('domain_access');

        $rules->apply($mailbox, $domainAccess, $request->senders(), $request->removed());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox rules saved.')]);

        return back();
    }

    /**
     * The one-time offer to import a new mailbox's older mail, turned down.
     * Taken up, it goes through the import dialog, and the import itself ends
     * the offer.
     */
    public function dismissImportOffer(Mailbox $mailbox): RedirectResponse
    {
        $mailbox->forceFill(['import_offer_answered_at' => now()])->save();

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
