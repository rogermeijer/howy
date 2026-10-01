<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Null on delete: knowledge-base files link to emails, and those links
            // have to survive a mailbox being removed.
            $table->foreignId('mailbox_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_message_id');
            $table->string('provider_thread_id')->nullable();
            $table->string('message_id_header', 512)->nullable();
            $table->string('in_reply_to', 512)->nullable();
            $table->string('subject', 998)->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('snippet')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('label_ids')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->string('source', 20);
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'mailbox_id', 'provider_message_id']);
            $table->index(['account_id', 'received_at']);
            $table->index(['account_id', 'provider_thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
