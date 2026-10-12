<?php

use App\Models\BetaAccessRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The private beta gets one table: a request for access *is* the invite, with
 * one single-use code. Separate vouchers were more machinery than a temporary
 * gate needs, so they go.
 *
 * Codes handed out by hand from the old vouchers table stop working; re-issue
 * them by adding the address in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beta_access_requests', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('locale');
            $table->timestamp('invited_at')->nullable()->after('code');
            $table->timestamp('redeemed_at')->nullable()->after('invited_at');
            // Who registered with the code, so a request can be traced to its account.
            $table->foreignId('user_id')->nullable()->after('redeemed_at')->constrained()->nullOnDelete();
        });

        BetaAccessRequest::query()->whereNull('code')->eachById(function (BetaAccessRequest $request): void {
            $request->forceFill(['code' => BetaAccessRequest::generateCode()])->save();
        });

        Schema::table('beta_access_requests', function (Blueprint $table) {
            $table->string('code', 32)->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
        });

        Schema::dropIfExists('vouchers');
    }

    public function down(): void
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

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('active_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('beta_access_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['code', 'invited_at', 'redeemed_at']);
        });
    }
};
