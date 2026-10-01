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

        Schema::create('document_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_sections')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->unsignedInteger('ordinal');
            $table->string('heading')->nullable();
            $table->text('heading_path');
            $table->unsignedInteger('page_from')->nullable();
            $table->unsignedInteger('page_to')->nullable();
            // Hash of the section's own normalised body: unchanged sections reuse
            // everything that was derived from them.
            $table->char('content_hash', 64);
            $table->unsignedInteger('token_count')->default(0);
            $table->foreignId('previous_section_id')->nullable()->constrained('document_sections')->nullOnDelete();
            $table->string('change_type', 20)->default('added');
            $table->timestamps();

            $table->index(['account_id', 'document_version_id', 'ordinal']);
            $table->index('content_hash');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) use ($dimensions) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Polymorphic so mail-derived knowledge later lands in the same index.
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('document_sections')->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->string('kind', 10)->default('text');
            $table->text('content');
            $table->text('context')->nullable();
            $table->unsignedInteger('page_from')->nullable();
            $table->unsignedInteger('page_to')->nullable();
            $table->unsignedInteger('token_count');
            $table->char('content_hash', 64);
            $table->vector('embedding', dimensions: $dimensions)->nullable();
            $table->string('embedding_model', 80)->nullable();
            // Only the current version of a source is searched.
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['account_id', 'is_current']);
            $table->index(['account_id', 'document_id']);
            $table->index('content_hash');
        });

        $this->addSearchColumns('knowledge_chunks', "coalesce(context, '') || ' ' || content");

        DB::statement(
            'CREATE INDEX knowledge_chunks_embedding_hnsw ON knowledge_chunks '
            .'USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64) WHERE is_current'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('document_sections');
    }

    /**
     * A regconfig column picks the stemmer per row (dutch / english), and the
     * tsvector is generated from it so it can never drift from the text.
     */
    private function addSearchColumns(string $table, string $text): void
    {
        DB::statement("ALTER TABLE {$table} ADD COLUMN search_config regconfig NOT NULL DEFAULT 'simple'");
        DB::statement(
            "ALTER TABLE {$table} ADD COLUMN search_vector tsvector "
            ."GENERATED ALWAYS AS (to_tsvector(search_config, {$text})) STORED"
        );
        DB::statement("CREATE INDEX {$table}_search_vector_gin ON {$table} USING gin (search_vector)");
    }
};
