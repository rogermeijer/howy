<?php

namespace App\Actions\Accounts;

use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\MailboxConnector;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an account and everything it owns.
 *
 * The database cascade takes every tenant row. What it cannot reach is done
 * here: connected mailboxes are disconnected first, so Gmail stops pushing and
 * the grant is revoked, and the account's files are removed from the knowledge
 * disk. Users stay; they only lose the membership.
 */
class DeleteAccount
{
    public function __construct(private readonly MailboxConnector $connector) {}

    public function handle(Account $account): void
    {
        Tenancy::for($account, function (): void {
            Mailbox::query()
                ->where('status', '!=', MailboxStatus::Disconnected)
                ->get()
                ->each(fn (Mailbox $mailbox) => $this->connector->disconnect($mailbox));
        });

        $id = $account->id;

        // Not a cascade: the pointer is nullOnDelete, but say it outright so the
        // next request heals them onto another membership.
        User::query()->where('active_account_id', $id)->update(['active_account_id' => null]);

        $account->delete();

        Storage::disk((string) config('knowledge.disk'))->deleteDirectory("accounts/{$id}");
    }
}
