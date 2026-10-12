<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enough about each AI call to understand how the app uses AI, not just what
 * it cost: which agent, how long it took, whether it failed, and the mail
 * thread it was for. Kept forever; the payloads live apart and are pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->string('agent', 80)->nullable()->after('step');
            $table->string('status', 20)->default('succeeded')->after('model');
            $table->text('error')->nullable()->after('status');
            $table->unsignedInteger('latency_ms')->nullable()->after('error');
            // Texts in one embeddings request.
            $table->unsignedInteger('input_count')->nullable()->after('latency_ms');
            // The provider thread of a mail subject, copied so per-thread cost
            // survives the mail itself being deleted.
            $table->string('thread_key')->nullable()->after('subject_id');

            $table->index(['account_id', 'step']);
            $table->index(['account_id', 'thread_key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'step']);
            $table->dropIndex(['account_id', 'thread_key']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['agent', 'status', 'error', 'latency_ms', 'input_count', 'thread_key']);
        });
    }
};
