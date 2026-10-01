<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('email_address');
            $table->string('provider_user_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 20)->default('active');
            // Gmail history ids are unsigned 64-bit, which overflows a signed bigint.
            $table->string('history_id', 32)->nullable();
            $table->timestamp('watch_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('import_batch_id', 36)->nullable();
            $table->string('send_policy', 20)->default('off');
            $table->json('send_allowlist')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'provider', 'email_address']);
            // The Gmail webhook resolves a push by address before it knows the account.
            $table->index('email_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
