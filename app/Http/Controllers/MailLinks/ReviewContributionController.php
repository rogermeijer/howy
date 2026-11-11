<?php

namespace App\Http\Controllers\MailLinks;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Services\Mail\Interpretation\SenderFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * What Howy took from a mail, for its sender to look over, from the button in
 * Saved! (04): per statement keep it, take it back, or say it is not right.
 * SenderFeedback says what follows from each.
 *
 * @phpstan-import-type Statement from EmailInterpretation
 */
class ReviewContributionController extends Controller
{
    use RendersMailLinkPages;

    public function show(Request $request): Response
    {
        if ($this->link($request)->expired()) {
            return $this->expiredPage($request);
        }

        $email = $this->email($request);
        $interpretation = $email->interpretation;
        $requests = collect($interpretation->sender_feedback ?? [])->keyBy('statement');

        return $this->page($request, 'mail-links/review', [
            'subject' => $email->subject,
            'statements' => collect($interpretation->statements ?? [])
                ->filter(fn (array $statement): bool => $statement['verdict'] === 'new')
                ->map(fn (array $statement, int $index): array => [
                    'index' => $index,
                    'statement' => $statement['statement'],
                    'review' => $statement['review'] ?? null,
                    'bySender' => $statement['by_sender'] ?? false,
                    'request' => $requests->has($index) ? [
                        'choice' => $requests[$index]['choice'],
                        'correction' => $requests[$index]['correction'],
                    ] : null,
                ])
                ->values()
                ->all(),
            'saved' => (bool) $request->session()->get('mail-link.saved'),
        ]);
    }

    public function store(Request $request, SenderFeedback $feedback): RedirectResponse
    {
        if ($this->link($request)->expired()) {
            return back();
        }

        $email = $this->email($request);
        $data = $request->validate([
            'requests' => ['present', 'array'],
            'requests.*.choice' => ['required', Rule::in(['keep', 'withdraw', 'correct'])],
            'requests.*.correction' => ['nullable', 'string', 'max:1000', 'required_if:requests.*.choice,correct'],
        ]);

        $requests = [];

        foreach ($data['requests'] as $index => $request) {
            if ($request['choice'] !== 'keep') {
                $requests[(int) $index] = [
                    'choice' => $request['choice'],
                    'correction' => $request['choice'] === 'correct' ? trim((string) $request['correction']) : null,
                ];
            }
        }

        $feedback->record($email->interpretation, $requests);

        return back()->with('mail-link.saved', true);
    }

    /**
     * The mail the link is for, through the account's scope.
     */
    private function email(Request $request): Email
    {
        $email = Email::query()->with('interpretation')->findOrFail($this->link($request)->recordId);

        abort_if($email->interpretation === null, 404);

        return $email;
    }
}
