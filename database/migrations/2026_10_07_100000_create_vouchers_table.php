<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invite codes for the private beta.
 *
 * Platform data, not account data: a voucher is redeemed before the account it
 * creates exists, so it carries no account_id and is never tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('note')->nullable();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Which code let a user in, so a handed-out batch can be traced back.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('active_account_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
        });

        Schema::dropIfExists('vouchers');
    }
};
