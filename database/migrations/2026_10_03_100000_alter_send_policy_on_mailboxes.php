<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replying now exists, so the policy gets its real shape: off, same domain,
     * whitelist or always, with a blacklist for the open ones. Always is the
     * default; mailboxes were only "off" because nothing could send yet.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->renameColumn('send_allowlist', 'send_whitelist');
        });

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->json('send_blacklist')->nullable()->after('send_whitelist');
            $table->string('send_policy', 20)->default('always')->change();
        });

        // Every account's mailboxes, on purpose: a migration has no tenant.
        DB::statement("UPDATE mailboxes SET send_policy = 'whitelist' WHERE send_policy = 'allowlist'");
        DB::statement("UPDATE mailboxes SET send_policy = 'always' WHERE send_policy = 'off'");
    }

    public function down(): void
    {
        DB::statement("UPDATE mailboxes SET send_policy = 'allowlist' WHERE send_policy = 'whitelist'");
        DB::statement("UPDATE mailboxes SET send_policy = 'off' WHERE send_policy = 'always'");

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('send_blacklist');
            $table->string('send_policy', 20)->default('off')->change();
        });

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->renameColumn('send_whitelist', 'send_allowlist');
        });
    }
};
