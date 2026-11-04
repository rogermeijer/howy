<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a reviewer chose for a fact waiting for approval, kept as they go:
 * for a conflict which fact holds ("new" or "keep"), for a new fact whether
 * to leave it out ("omit"). Approving acts on it; until then it can change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->string('review_choice', 10)->nullable()->after('review_explanation');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->dropColumn('review_choice');
        });
    }
};
