<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What Howy was allowed to do with a mail's sender when it arrived: learn,
     * answer, both or neither. Every mail read before was read in full.
     */
    public function up(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->string('sender_access', 20)->default('learn_answer')->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->dropColumn('sender_access');
        });
    }
};
