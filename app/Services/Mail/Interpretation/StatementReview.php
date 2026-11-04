<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\FactStatus;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A person decides on what a mail would add to or change in the knowledge
 * base. Approving a new statement makes its proposed fact count; approving a
 * conflicting one replaces the fact it contradicts. Rejecting keeps it out.
 *
 * @phpstan-import-type Statement from EmailInterpretation
 */
class StatementReview
{
    public function __construct(private readonly MailFactWriter $facts) {}

    public function approve(EmailInterpretation $interpretation, int $index, User $reviewer): void
    {
        $this->decide($interpretation, $index, $reviewer, function (array $statement, Email $email) use ($interpretation): array {
            $fact = $statement['fact_id'] !== null ? KnowledgeFact::query()->find($statement['fact_id']) : null;

            if ($fact !== null) {
                $fact->update(['status' => FactStatus::Supplementary]);
            } else {
                // A conflict, or a proposed fact that has gone since.
                $fact = $this->facts->write($email, [$statement], FactStatus::Supplementary, $interpretation->language)[0];
            }

            // The fact it contradicts no longer holds: this one replaces it.
            if ($statement['verdict'] === 'conflict' && $statement['existing_fact_id'] !== null) {
                KnowledgeFact::query()
                    ->whereKey($statement['existing_fact_id'])
                    ->where('status', '!=', FactStatus::Expired)
                    ->update([
                        'status' => FactStatus::Expired,
                        'valid_until' => now()->toDateString(),
                        'superseded_by_id' => $fact->id,
                    ]);
            }

            return [...$statement, 'fact_id' => $fact->id, 'review' => 'approved'];
        });
    }

    public function reject(EmailInterpretation $interpretation, int $index, User $reviewer): void
    {
        $this->decide($interpretation, $index, $reviewer, function (array $statement): array {
            if ($statement['fact_id'] !== null) {
                KnowledgeFact::query()->whereKey($statement['fact_id'])->where('status', FactStatus::Proposed)->delete();
            }

            return [...$statement, 'fact_id' => null, 'review' => 'rejected'];
        });
    }

    /**
     * @param  callable(Statement, Email): Statement  $decision
     */
    private function decide(EmailInterpretation $interpretation, int $index, User $reviewer, callable $decision): void
    {
        $statements = $interpretation->statements ?? [];
        $statement = $statements[$index] ?? null;

        if ($statement === null || $statement['review'] !== 'pending') {
            throw new InvalidArgumentException('This statement is not waiting for review.');
        }

        DB::transaction(function () use ($interpretation, $statements, $statement, $index, $reviewer, $decision): void {
            $statements[$index] = [
                ...$decision($statement, $interpretation->email),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now()->toIso8601String(),
            ];

            $interpretation->update([
                'statements' => $statements,
                'needs_review' => in_array('pending', array_column($statements, 'review'), true),
            ]);
        });
    }
}
