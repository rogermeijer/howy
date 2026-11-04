<?php

use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A version is processed first and published only when an administrator
     * approves it. What is already in the knowledge base was live before this
     * existed, so it counts as approved.
     */
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('processed_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });

        // Every account's rows, on purpose: a migration has no tenant.
        Tenancy::withoutTenancy(fn () => DocumentVersion::query()
            ->whereNull('approved_at')
            ->update(['approved_at' => DB::raw('coalesce(processed_at, created_at)')]));
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
        });
    }
};
