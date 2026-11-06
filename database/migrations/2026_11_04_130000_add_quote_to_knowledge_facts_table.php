<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The words in the source that state a fact, exactly as they stand there and
 * in the source's own language: what a reviewer is shown when checking a
 * fact, however it was worded or translated for the knowledge base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->text('quote')->nullable()->after('statement');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->dropColumn('quote');
        });
    }
};
