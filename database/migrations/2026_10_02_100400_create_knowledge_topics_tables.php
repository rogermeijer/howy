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

        Schema::create('knowledge_topics', function (Blueprint $table) use ($dimensions) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('knowledge_topics')->cascadeOnDelete();
            $table->unsignedSmallInteger('depth');
            $table->string('kind', 20)->default('theme');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('summary_stale')->default(true);
            $table->string('origin', 10)->default('ai');
            $table->string('review_status', 10)->default('new');
            $table->vector('embedding', dimensions: $dimensions)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'parent_id']);
            $table->index(['account_id', 'review_status']);
        });

        // Ids, not names, make up the path, so renaming never rewrites a subtree.
        DB::statement("ALTER TABLE knowledge_topics ADD COLUMN path ltree NOT NULL DEFAULT ''");
        DB::statement('CREATE INDEX knowledge_topics_path_gist ON knowledge_topics USING gist (path)');
        DB::statement('ALTER TABLE knowledge_topics ADD CONSTRAINT knowledge_topics_depth_check CHECK (depth BETWEEN 1 AND 3)');
        // Postgres treats NULLs as distinct, so a top-level slug needs its own index.
        DB::statement(
            'CREATE UNIQUE INDEX knowledge_topics_account_parent_slug_unique '
            .'ON knowledge_topics (account_id, coalesce(parent_id, 0), slug)'
        );

        Schema::create('knowledge_topic_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('knowledge_topics')->cascadeOnDelete();
            $table->string('linkable_type', 40);
            $table->unsignedBigInteger('linkable_id');
            $table->decimal('relevance', 3, 2)->default(1);
            $table->string('origin', 10)->default('ai');
            $table->timestamps();

            $table->unique(['account_id', 'topic_id', 'linkable_type', 'linkable_id']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_topic_links');
        Schema::dropIfExists('knowledge_topics');
    }
};
