<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // What the sender wrote in this message, without the quoted history:
            // sanitised HTML for display, plain text for snippets and search.
            $table->longText('content_html')->nullable()->after('body_html');
            $table->longText('content_text')->nullable()->after('content_html');
            // The quoted history, one entry per earlier message, newest first.
            $table->json('quotes')->nullable()->after('content_text');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn(['content_html', 'content_text', 'quotes']);
        });
    }
};
