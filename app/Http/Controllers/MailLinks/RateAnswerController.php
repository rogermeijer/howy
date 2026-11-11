<?php

namespace App\Http\Controllers\MailLinks;

use App\Enums\AnswerRating;
use App\Http\Controllers\Controller;
use App\Models\EmailInterpretation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Rating an answer Howy gave, from the links under it (05). Each link opens
 * this page with its choice made; nothing is saved until it is sent, because
 * link scanners in mail (Outlook's Safe Links, say) open every link.
 */
class RateAnswerController extends Controller
{
    use RendersMailLinkPages;

    public function show(Request $request): Response
    {
        if ($this->link($request)->expired()) {
            return $this->expiredPage($request);
        }

        $interpretation = $this->interpretation($request);

        return $this->page($request, 'mail-links/rate', [
            'question' => $interpretation->question,
            'answer' => $interpretation->answer,
            'choice' => AnswerRating::tryFrom($request->string('choice')->toString())->value ?? $interpretation->rating?->value,
            'note' => $interpretation->rating_note,
            'rated' => $interpretation->rated_at !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->link($request)->expired()) {
            return back();
        }

        $interpretation = $this->interpretation($request);
        $data = $request->validate([
            'rating' => ['required', Rule::enum(AnswerRating::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $interpretation->forceFill([
            'rating' => $data['rating'],
            'rating_note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
            'rated_at' => now(),
        ])->save();

        return back();
    }

    /**
     * The answer the link is for, through the account's scope.
     */
    private function interpretation(Request $request): EmailInterpretation
    {
        return EmailInterpretation::query()->whereKey($this->link($request)->recordId)->whereNotNull('answer')->firstOrFail();
    }
}
