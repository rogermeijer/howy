<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Every header as received, in order and with repeats (Received,
            // DKIM-Signature…), as a list of {name, value} pairs.
            $table->json('headers')->nullable()->after('in_reply_to');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('headers');
        });
    }
};
