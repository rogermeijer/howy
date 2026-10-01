<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_interpretations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('intent', 20)->nullable();
            $table->decimal('intent_confidence', 3, 2)->nullable();
            $table->string('outcome', 20)->nullable();
            // How the mail was read, in one line in its own language.
            $table->text('summary')->nullable();
            $table->string('language', 10)->nullable();
            $table->text('question')->nullable();
            $table->text('answer')->nullable();
            $table->json('citations')->nullable();
            // Every statement with its verdict; held conflicts live only here.
            $table->json('statements')->nullable();
            $table->string('reply_status', 20)->default('none');
            $table->text('reply_text')->nullable();
            $table->string('reply_provider_message_id')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'email_id']);
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_interpretations');
    }
};
