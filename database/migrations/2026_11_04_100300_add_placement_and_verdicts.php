<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Before a file is added, it is held against what is known: is its text
     * already there under another file, is it a new version of a document,
     * and which of its facts are new, known already or contradict one.
     */
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->char('text_sha256', 64)->nullable()->after('sha256');
            // Where it was put and why: new, a version of a document, or a hint.
            $table->json('placement')->nullable()->after('extracted_path');
            $table->index(['account_id', 'text_sha256']);
        });

        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->string('review_verdict', 16)->nullable()->after('status');
            $table->foreignId('related_fact_id')->nullable()->after('review_verdict')->constrained('knowledge_facts')->nullOnDelete();
            $table->text('review_explanation')->nullable()->after('related_fact_id');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_fact_id');
            $table->dropColumn(['review_verdict', 'review_explanation']);
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'text_sha256']);
            $table->dropColumn(['text_sha256', 'placement']);
        });
    }
};
