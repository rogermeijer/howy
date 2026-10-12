<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was sent to the AI and what came back, for one call. Customer text, so
 * encrypted at rest and pruned after knowledge.ai_log.payload_retention_days;
 * the usage record it belongs to stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_call_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_usage_record_id')->constrained()->cascadeOnDelete();
            $table->longText('instructions')->nullable();
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            // Names and types only, never the files.
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['account_id', 'ai_usage_record_id']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_payloads');
    }
};
