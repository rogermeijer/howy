<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email addresses asking for a private beta invite.
 *
 * Kept on their own, deliberately unlinked to users or accounts: a request is
 * made by someone who has neither yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 5)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_access_requests');
    }
};
