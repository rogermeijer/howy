<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->foreignId('active_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('locale', 5)->nullable();
            $table->string('timezone', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_account_id');
            $table->dropColumn(['is_admin', 'locale', 'timezone']);
        });
    }
};
