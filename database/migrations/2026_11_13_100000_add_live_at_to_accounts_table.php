<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The start signal: from when an account is live, Howy sends its own mails to
 * colleagues (the welcome and what follows). Answers to questions never wait
 * for it. Every account starts not live; an admin sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->timestamp('live_at')->nullable()->after('onboarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('live_at');
        });
    }
};
