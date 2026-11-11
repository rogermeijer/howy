<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\EmailIntent;
use App\Enums\InterpretationMode;
use App\Enums\InterpretationOutcome;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\User;

/**
 * An interpretation as the pages show it: how the mail was read, what
 * followed, and where every fact it touched comes from.
 *
 * @phpstan-import-type Citation from EmailInterpretation
 */
class InterpretationPresenter
{
    /**
     * The short form for a list row.
     *
     * @return array{status: string, statusLabel: string, intent: string|null, intentLabel: string|null, outcome: string|null, outcomeLabel: string|null, rating: string|null, ratingLabel: string|null}
     */
    public function brief(EmailInterpretation $interpretation): array
    {
        return [
            'status' => $interpretation->status->value,
            'statusLabel' => $interpretation->status->label(),
            'intent' => $interpretation->intent?->value,
            'intentLabel' => $interpretation->intent?->label(),
            'outcome' => $interpretation->outcome?->value,
            'outcomeLabel' => $interpretation->outcome?->label(),
            // How whoever asked rated the answer, from the links under it.
            'rating' => $interpretation->rating?->value,
            'ratingLabel' => $interpretation->rating?->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(EmailInterpretation $interpretation): array
    {
        return [
            ...$this->brief($interpretation),
            'mode' => $interpretation->mode->value,
            'modeLabel' => $interpretation->mode->label(),
            'senderAccess' => $interpretation->sender_access->value,
            'senderAccessLabel' => $interpretation->sender_access->label(),
            'note' => $this->note($interpretation),
            'confidence' => $interpretation->intent_confidence,
            'answerConfidence' => $interpretation->answer_confidence,
            'answerGaps' => $interpretation->answer_gaps,
            'summary' => $interpretation->summary ?? ($interpretation->outcome === InterpretationOutcome::Duplicate && $interpretation->statements === null
                ? __('Nothing new: this mail only repeats what is already known.')
                : null),
            'question' => $interpretation->question,
            'answer' => $interpretation->answer,
            'citations' => array_map($this->citation(...), $interpretation->citations ?? []),
            'statements' => $this->statements($interpretation),
            'replyStatus' => $interpretation->reply_status->value,
            'replyStatusLabel' => $interpretation->reply_status->label(),
            'replyText' => $interpretation->reply_text,
            'replyRecipients' => $interpretation->reply_recipients ?? [],
            'repliedAt' => $interpretation->replied_at?->toIso8601String(),
            'ratingNote' => $interpretation->rating_note,
            'ratedAt' => $interpretation->rated_at?->toIso8601String(),
            'error' => $interpretation->error,
            'processedAt' => $interpretation->processed_at?->toIso8601String(),
        ];
    }

    /**
     * Why nothing followed a mail Howy read, when the mailbox's rules for its
     * sender are the reason.
     */
    private function note(EmailInterpretation $interpretation): ?string
    {
        return match (true) {
            $interpretation->intent === EmailIntent::Question
                && $interpretation->mode === InterpretationMode::Addressed
                && ! $interpretation->sender_access->answers() => (string) __('Not answered: Howy only learns from this sender.'),
            $interpretation->intent === EmailIntent::Information
                && ! $interpretation->sender_access->learns() => (string) __('Not learned: Howy only answers this sender.'),
            default => null,
        };
    }

    /**
     * Every statement with its verdict, where the fact it was held against
     * comes from, and who decided on it. The position in the list is what a
     * decision on it refers to.
     *
     * @return list<array<string, mixed>>
     */
    public function statements(EmailInterpretation $interpretation): array
    {
        $statements = $interpretation->statements ?? [];
        $existing = $this->existingSources(array_values(array_filter(array_column($statements, 'existing_fact_id'))));
        $reviewers = User::query()->whereKey(array_values(array_filter(array_column($statements, 'reviewed_by'))))->pluck('name', 'id');
        $requests = collect($interpretation->sender_feedback ?? [])->keyBy('statement');

        return array_map(fn (array $statement, int $index): array => [
            'index' => $index,
            'statement' => $statement['statement'],
            'verdict' => $statement['verdict'],
            'factId' => $statement['fact_id'],
            'existingStatement' => $statement['existing_statement'],
            'existingSource' => $statement['existing_fact_id'] !== null ? ($existing[$statement['existing_fact_id']] ?? null) : null,
            'explanation' => $statement['explanation'],
            'flag' => $statement['flag'] ?? null,
            'fromHistory' => $statement['from_history'] ?? false,
            'review' => $statement['review'] ?? null,
            'reviewedBy' => isset($statement['reviewed_by']) ? ($reviewers[$statement['reviewed_by']] ?? null) : null,
            'reviewedAt' => $statement['reviewed_at'] ?? null,
            // Taken back by whoever sent the mail, from the page linked in Saved! (04).
            'bySender' => $statement['by_sender'] ?? false,
            'senderRequest' => $requests->has($index) ? [
                'choice' => $requests[$index]['choice'],
                'correction' => $requests[$index]['correction'],
                'at' => $requests[$index]['at'],
                'handled' => ($requests[$index]['handled_at'] ?? null) !== null,
            ] : null,
        ], $statements, array_keys($statements));
    }

    /**
     * @param  Citation  $citation
     * @return array{type: string, id: int, title: string, sectionId: int|null, headingPath: string|null, page: int|null}
     */
    private function citation(array $citation): array
    {
        return [
            'type' => $citation['type'],
            'id' => $citation['id'],
            'title' => $citation['title'],
            'sectionId' => $citation['section_id'],
            'headingPath' => $citation['heading_path'],
            'page' => $citation['page'],
        ];
    }

    /**
     * Where each fact a statement was compared with comes from, by fact id.
     * Facts deleted since (a document removed) simply have no source.
     *
     * @param  list<int>  $ids
     * @return array<int, array{type: string, id: int, title: string, sectionId: int|null, headingPath: string|null, page: int|null}>
     */
    private function existingSources(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $facts = KnowledgeFact::query()->with(['document', 'section'])->whereKey($ids)->get();
        $subjects = Email::query()->whereKey($facts->where('source_type', 'email')->pluck('source_id')->all())->pluck('subject', 'id');
        $sources = [];

        foreach ($facts as $fact) {
            $sources[$fact->id] = $fact->source_type === 'email'
                ? ['type' => 'email', 'id' => $fact->source_id, 'title' => (string) ($subjects[$fact->source_id] ?? ''), 'sectionId' => null, 'headingPath' => null, 'page' => null]
                : ['type' => 'document', 'id' => (int) $fact->document_id, 'title' => $fact->documentTitle(), 'sectionId' => $fact->section_id, 'headingPath' => $fact->section?->heading_path, 'page' => $fact->page_from];
        }

        return $sources;
    }
}
