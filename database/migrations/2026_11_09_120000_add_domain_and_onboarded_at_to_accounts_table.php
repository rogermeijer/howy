<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The domain the company mails from, so Howy can suggest an address of its own
 * there (howy@domain), and when an administrator finished the setup wizard.
 * Accounts that exist already were set up by hand, so they count as set up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('domain', 253)->nullable()->after('name');
            $table->timestamp('onboarded_at')->nullable()->after('timezone');
        });

        // Accounts are not tenant-scoped: they are the tenant.
        Account::query()->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['domain', 'onboarded_at']);
        });
    }
};
