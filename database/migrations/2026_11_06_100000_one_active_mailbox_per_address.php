<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A Gmail address is active in one account at most. Disconnecting a mailbox
 * revokes the grant at Google, which would silently unlink a second account
 * using the same address.
 *
 * The one unique index on a tenant table without account_id, on purpose: it has
 * to hold across accounts (CLAUDE.md rule 10). Partial, because disconnected and
 * needs-reauth mailboxes keep their row and emails, and must never block
 * connecting the address somewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX mailboxes_one_active_per_address ON mailboxes (provider, email_address) '
            ."WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX mailboxes_one_active_per_address');
    }
};
