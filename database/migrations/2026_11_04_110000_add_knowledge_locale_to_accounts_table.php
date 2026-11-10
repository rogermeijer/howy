<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language the knowledge base is built in: what Howy writes itself
 * (facts, summaries, folder names, statements from mail). Separate from the
 * account's default interface language; until someone sets it, the same.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('knowledge_locale', 10)->nullable()->after('locale');
        });

        // Accounts are not tenant-scoped: they are the tenant.
        Account::query()->each(fn (Account $account) => $account->forceFill(['knowledge_locale' => $account->locale])->save());

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('knowledge_locale', 10)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('knowledge_locale');
        });
    }
};
