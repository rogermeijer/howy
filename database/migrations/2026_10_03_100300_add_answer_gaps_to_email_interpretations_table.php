<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a question asked that the knowledge base could not tell: said in
     * the answer, and given as context when someone answers it in the thread.
     */
    public function up(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->text('answer_gaps')->nullable()->after('answer_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->dropColumn('answer_gaps');
        });
    }
};
