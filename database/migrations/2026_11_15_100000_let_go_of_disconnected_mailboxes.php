<?php

use App\Facades\Tenancy;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Disconnecting a mailbox now lets go of whatever leaned on it (see
     * MailboxConnector::disconnect()). Whoever had it as their home mailbox
     * has none, and what waited to go out from it is skipped. This does the
     * same for the mailboxes that were disconnected before.
     */
    public function up(): void
    {
        // Every account's mailboxes, on purpose: a migration has no tenant.
        Tenancy::withoutTenancy(function (): void {
            $disconnected = Mailbox::query()->where('status', 'disconnected')->select('id');

            Colleague::query()->whereIn('home_mailbox_id', $disconnected)->update(['home_mailbox_id' => null]);

            OutboundMail::query()
                ->whereIn('mailbox_id', $disconnected)
                ->where('status', 'pending')
                ->update(['status' => 'skipped', 'skip_reason' => 'no_mailbox']);
        });
    }

    /**
     * Nothing to undo: which mailbox each of them had is gone, and a
     * disconnected mailbox sends nothing either way.
     */
    public function down(): void {}
};
