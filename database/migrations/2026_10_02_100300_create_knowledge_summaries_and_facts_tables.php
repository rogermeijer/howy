<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dimensions = (int) config('knowledge.embeddings.dimensions');

        Schema::create('knowledge_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('summarizable_type', 40);
            $table->unsignedBigInteger('summarizable_id');
            $table->text('text');
            $table->unsignedInteger('token_count')->default(0);
            // Hash of what the summary was made from: equal input, no new call.
            $table->char('input_hash', 64);
            $table->string('model', 80);
            $table->timestamps();

            $table->unique(['account_id', 'summarizable_type', 'summarizable_id']);
        });

        Schema::create('knowledge_facts', function (Blueprint $table) use ($dimensions) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('document_sections')->nullOnDelete();
            $table->foreignId('chunk_id')->nullable()->constrained('knowledge_chunks')->nullOnDelete();
            $table->text('statement');
            $table->string('subject', 120)->nullable();
            $table->string('status', 20)->default('core');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('knowledge_facts')->nullOnDelete();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->unsignedInteger('page_from')->nullable();
            $table->unsignedInteger('page_to')->nullable();
            $table->char('content_hash', 64);
            $table->vector('embedding', dimensions: $dimensions)->nullable();
            $table->string('embedding_model', 80)->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'document_id']);
        });

        DB::statement("ALTER TABLE knowledge_facts ADD COLUMN search_config regconfig NOT NULL DEFAULT 'simple'");
        DB::statement(
            'ALTER TABLE knowledge_facts ADD COLUMN search_vector tsvector '
            ."GENERATED ALWAYS AS (to_tsvector(search_config, coalesce(subject, '') || ' ' || statement)) STORED"
        );
        DB::statement('CREATE INDEX knowledge_facts_search_vector_gin ON knowledge_facts USING gin (search_vector)');
        DB::statement(
            'CREATE INDEX knowledge_facts_embedding_hnsw ON knowledge_facts '
            ."USING hnsw (embedding vector_cosine_ops) WHERE status <> 'expired'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_facts');
        Schema::dropIfExists('knowledge_summaries');
    }
};
