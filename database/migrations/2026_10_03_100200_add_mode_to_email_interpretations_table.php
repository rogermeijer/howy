<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mail the mailbox is only copied on is interpreted too, in a listening
     * role: the mode says which role, the confidence decides whether an answer
     * is worth suggesting, and the recipients record who a reply went to.
     */
    public function up(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->string('mode', 20)->default('addressed')->after('status');
            $table->decimal('answer_confidence', 3, 2)->nullable()->after('answer');
            $table->json('reply_recipients')->nullable()->after('reply_text');
        });
    }

    public function down(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->dropColumn(['mode', 'answer_confidence', 'reply_recipients']);
        });
    }
};
