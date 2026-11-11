<?php

namespace App\Services\Mail\Interpretation;

use App\Models\EmailInterpretation;
use Illuminate\Support\Facades\DB;

/**
 * What a sender asks to change about what Howy took from their mail, from the
 * page linked in Saved! (04): take a statement back, or say it is not right,
 * with a correction.
 *
 * A statement still waiting for approval is turned down at once, as by the
 * sender. One already in the knowledge base cannot be taken back by them: the
 * request waits for an admin, who sees it on the mail and in the approvals,
 * and marks it done. A correction waits for an admin either way, since it
 * may be what the knowledge base should say instead.
 *
 * @phpstan-import-type SenderFeedback from EmailInterpretation as SenderRequest
 */
class SenderFeedback
{
    public function __construct(private readonly StatementReview $review) {}

    /**
     * @param  array<int, array{choice: 'withdraw'|'correct', correction: string|null}>  $requests  by statement index
     */
    public function record(EmailInterpretation $interpretation, array $requests): void
    {
        DB::transaction(function () use ($interpretation, $requests): void {
            foreach ($requests as $index => $request) {
                $statement = ($interpretation->statements ?? [])[$index] ?? null;

                if ($statement === null || $statement['verdict'] !== 'new' || ($statement['review'] ?? null) === 'rejected') {
                    continue;
                }

                $waiting = ($statement['review'] ?? null) === 'pending';

                if ($waiting) {
                    $this->review->rejectBySender($interpretation, $index);
                    $interpretation->refresh();
                }

                $this->save($interpretation, [
                    'statement' => $index,
                    'choice' => $request['choice'],
                    'correction' => $request['correction'],
                    'at' => now()->toIso8601String(),
                    // Taken back before it counted: nothing left for an admin.
                    'handled_at' => $waiting && $request['choice'] === 'withdraw' ? now()->toIso8601String() : null,
                ]);
            }
        });
    }

    /**
     * An admin dealt with the sender's request about a statement.
     */
    public function handle(EmailInterpretation $interpretation, int $index): void
    {
        $feedback = array_map(
            fn (array $request): array => $request['statement'] === $index && ($request['handled_at'] ?? null) === null
                ? [...$request, 'handled_at' => now()->toIso8601String()]
                : $request,
            $interpretation->sender_feedback ?? [],
        );

        $interpretation->forceFill([
            'sender_feedback' => $feedback,
            'needs_review' => $interpretation->waitsForReview(feedback: $feedback),
        ])->save();
    }

    /**
     * The latest request about a statement replaces an earlier one.
     *
     * @param  SenderRequest  $request
     */
    private function save(EmailInterpretation $interpretation, array $request): void
    {
        $feedback = [
            ...array_values(array_filter($interpretation->sender_feedback ?? [], fn (array $earlier): bool => $earlier['statement'] !== $request['statement'])),
            $request,
        ];

        $interpretation->forceFill([
            'sender_feedback' => $feedback,
            'needs_review' => $interpretation->waitsForReview(feedback: $feedback),
        ])->save();
    }
}
