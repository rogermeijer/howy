<?php

use App\Enums\FactStatus;
use App\Facades\Tenancy;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a mail would add to the knowledge base now waits for review. The
     * flag makes "how many mails need action" one indexed count; what was
     * already added from mail goes back to review, as if it came in today.
     */
    public function up(): void
    {
        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->after('outcome');
            $table->index(['account_id', 'needs_review']);
        });

        // Every account's rows, on purpose: a migration has no tenant.
        Tenancy::withoutTenancy(function (): void {
            KnowledgeFact::query()
                ->where('source_type', 'email')
                ->where('status', FactStatus::Supplementary)
                ->update(['status' => FactStatus::Proposed]);

            EmailInterpretation::query()->whereNotNull('statements')->each(function (EmailInterpretation $interpretation): void {
                $statements = array_map(fn (array $statement): array => [
                    ...$statement,
                    'flag' => $statement['flag'] ?? null,
                    'review' => $statement['review'] ?? (in_array($statement['verdict'], ['new', 'conflict'], true) ? 'pending' : null),
                    'reviewed_by' => $statement['reviewed_by'] ?? null,
                    'reviewed_at' => $statement['reviewed_at'] ?? null,
                ], $interpretation->statements ?? []);

                $interpretation->forceFill([
                    'statements' => $statements,
                    'needs_review' => in_array('pending', array_column($statements, 'review'), true),
                ])->saveQuietly();
            });
        });
    }

    public function down(): void
    {
        Tenancy::withoutTenancy(fn () => KnowledgeFact::query()
            ->where('source_type', 'email')
            ->where('status', FactStatus::Proposed)
            ->update(['status' => FactStatus::Supplementary]));

        Schema::table('email_interpretations', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'needs_review']);
            $table->dropColumn('needs_review');
        });
    }
};
