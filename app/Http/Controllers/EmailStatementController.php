<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Services\Mail\Interpretation\StatementReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * Approve or reject what a mail would add to or change in the knowledge base.
 *
 * Typed binding, so another account's email 404s through the tenant scope.
 */
class EmailStatementController extends Controller
{
    public function approve(Request $request, Email $email, int $statement, StatementReview $review): RedirectResponse
    {
        return $this->decide(fn () => $review->approve($this->interpretation($email), $statement, $request->user()), __('Added to the knowledge base.'));
    }

    public function reject(Request $request, Email $email, int $statement, StatementReview $review): RedirectResponse
    {
        return $this->decide(fn () => $review->reject($this->interpretation($email), $statement, $request->user()), __('Kept out of the knowledge base.'));
    }

    private function decide(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException) {
            abort(409, __('This statement is not waiting for review.'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function interpretation(Email $email): EmailInterpretation
    {
        $interpretation = $email->interpretation;
        abort_if($interpretation === null, 404);

        return $interpretation;
    }
}
