<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * pgvector for embeddings, ltree for the topic folder tree.
 *
 * CREATE EXTENSION needs a superuser. On a server where the app's database user
 * is not one, run `CREATE EXTENSION vector; CREATE EXTENSION ltree;` once as the
 * postgres user; this migration is then a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();
        Schema::ensureExtensionExists('ltree');
    }

    public function down(): void
    {
        // Left in place: other databases on the server may rely on them.
    }
};
