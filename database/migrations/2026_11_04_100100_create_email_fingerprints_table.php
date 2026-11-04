<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mail is read once. What a mail said is remembered as hashes of its
     * paragraphs and of the whole message, so a forward, a reply quoting it
     * or the same mail in a second mailbox is recognised and not read again.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->char('content_hash', 64)->nullable()->after('quotes');
            $table->index(['account_id', 'message_id_header']);
            $table->index(['account_id', 'content_hash']);
        });

        Schema::create('email_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // The mail in which it was first read.
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->char('hash', 64);
            $table->unsignedInteger('char_count');
            $table->string('handled_as', 16);
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'kind', 'hash']);
            $table->index(['account_id', 'email_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_fingerprints');

        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'content_hash']);
            $table->dropIndex(['account_id', 'message_id_header']);
            $table->dropColumn('content_hash');
        });
    }
};
