<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every mail Howy sends from a mailbox, logged before it goes: the mail
     * log, the queue for mail waiting on a mailbox, and what a reply to Howy
     * is recognised by. A mail that comes in as a reply to one of them says
     * which (emails.outbound_mail_id).
     */
    public function up(): void
    {
        Schema::create('outbound_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('colleague_id')->nullable()->constrained()->nullOnDelete();
            // The mail it answers, or is about.
            $table->foreignId('email_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30);
            $table->string('status', 20);
            $table->string('skip_reason', 30)->nullable();
            $table->string('recipient');
            $table->json('cc')->nullable();
            $table->string('subject')->nullable();
            $table->longText('text');
            $table->longText('html');
            // In-Reply-To, References, List-Unsubscribe…: what the message carries besides.
            $table->json('headers')->nullable();
            // Our own Message-ID, signed, and the one Gmail sent it with.
            $table->string('reference')->nullable();
            $table->string('message_id_header')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('provider_thread_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'colleague_id']);
            $table->index(['mailbox_id', 'message_id_header']);
            $table->index(['mailbox_id', 'provider_thread_id']);
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->foreignId('outbound_mail_id')->nullable()->after('colleague_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outbound_mail_id');
        });

        Schema::dropIfExists('outbound_mails');
    }
};
