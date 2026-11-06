<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A mailbox that has never imported anything offers, once, to import older
     * mail. Turned down, it asks no more; an import ends it too.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->timestamp('import_offer_answered_at')->nullable()->after('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('import_offer_answered_at');
        });
    }
};
