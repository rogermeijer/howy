<?php

use App\Facades\Tenancy;
use App\Models\Email;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeFactSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every place a fact is stated: a section of a document, a mail. The same
 * claim found again in another document or mail is one fact with one more
 * source, not a second fact. The fact's own source columns stay its primary
 * source; this table is the full list.
 *
 * A fact no longer goes when its document does: removing a document is a
 * choice about its facts, made by whoever removes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_fact_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_fact_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('document_sections')->nullOnDelete();
            $table->foreignId('chunk_id')->nullable()->constrained('knowledge_chunks')->nullOnDelete();
            $table->foreignId('email_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('page_from')->nullable();
            $table->unsignedInteger('page_to')->nullable();
            // Named when it was added, so a source that is removed later can still be named.
            $table->string('title');
            $table->timestamps();

            $table->index(['account_id', 'knowledge_fact_id']);
            $table->index(['account_id', 'document_id']);
            $table->index(['account_id', 'section_id']);
            $table->index(['account_id', 'email_id']);
        });

        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
        });

        // Every fact so far has one source: its own.
        Tenancy::withoutTenancy(fn () => KnowledgeFact::query()->with('document')->eachById(function (KnowledgeFact $fact): void {
            $mail = $fact->source_type === 'email';
            $subject = $mail ? Email::query()->whereKey($fact->source_id)->value('subject') : null;

            (new KnowledgeFactSource)->forceFill([
                'account_id' => $fact->account_id,
                'knowledge_fact_id' => $fact->id,
                'kind' => $mail ? 'mail' : 'document',
                'document_id' => $mail ? null : $fact->document_id,
                'document_version_id' => $mail ? null : $fact->document_version_id,
                'section_id' => $mail ? null : $fact->section_id,
                'chunk_id' => $mail ? null : $fact->chunk_id,
                'email_id' => $mail && $subject !== null ? $fact->source_id : null,
                'page_from' => $fact->page_from,
                'page_to' => $fact->page_to,
                'title' => (string) ($mail ? $subject : $fact->document?->title),
                'created_at' => $fact->created_at,
                'updated_at' => $fact->created_at,
            ])->save();
        }));
    }

    public function down(): void
    {
        Schema::table('knowledge_facts', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });

        Schema::dropIfExists('knowledge_fact_sources');
    }
};
