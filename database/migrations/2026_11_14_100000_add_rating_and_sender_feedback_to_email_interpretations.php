<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What came back from a link in one of Howy's mails: how whoever asked
     * rated an answer, and what a sender asked to change about what Howy took
     * from their mail.
     */
    public function up(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->string('rating', 20)->nullable()->after('replied_at');
            $table->text('rating_note')->nullable()->after('rating');
            $table->timestamp('rated_at')->nullable()->after('rating_note');
            $table->json('sender_feedback')->nullable()->after('rated_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_note', 'rated_at', 'sender_feedback']);
        });
    }
};
