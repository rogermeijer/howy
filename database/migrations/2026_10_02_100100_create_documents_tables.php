<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20)->default('other');
            // Only core documents yield facts, the claims mails get checked against.
            $table->boolean('is_core')->default(false);
            $table->string('language', 5);
            $table->date('effective_date')->nullable();
            // Set once the first version exists; the FK is added below.
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'type']);
            $table->index(['account_id', 'updated_at']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('disk', 40);
            $table->string('path');
            $table->char('sha256', 64);
            $table->unsignedInteger('page_count')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            // The extracted intermediate format, so re-chunking never re-extracts.
            $table->string('extracted_path')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // An identical file is never processed twice within an account.
            $table->unique(['account_id', 'sha256']);
            $table->unique(['account_id', 'document_id', 'version_number']);
            $table->index(['account_id', 'status']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('document_versions')->nullOnDelete();
        });

        Schema::create('document_processing_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->string('step', 20);
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->string('provider_batch_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'document_version_id', 'step']);
            $table->index('provider_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('document_processing_steps');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
