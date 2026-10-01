<?php

namespace App\Services\Mail\Interpretation;

use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;

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
     * @return array{status: string, statusLabel: string, intent: string|null, intentLabel: string|null, outcome: string|null, outcomeLabel: string|null}
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(EmailInterpretation $interpretation): array
    {
        $statements = $interpretation->statements ?? [];
        $existing = $this->existingSources(array_values(array_filter(array_column($statements, 'existing_fact_id'))));

        return [
            ...$this->brief($interpretation),
            'mode' => $interpretation->mode->value,
            'modeLabel' => $interpretation->mode->label(),
            'confidence' => $interpretation->intent_confidence,
            'answerConfidence' => $interpretation->answer_confidence,
            'answerGaps' => $interpretation->answer_gaps,
            'summary' => $interpretation->summary,
            'question' => $interpretation->question,
            'answer' => $interpretation->answer,
            'citations' => array_map($this->citation(...), $interpretation->citations ?? []),
            'statements' => array_map(fn (array $statement): array => [
                'statement' => $statement['statement'],
                'verdict' => $statement['verdict'],
                'factId' => $statement['fact_id'],
                'existingStatement' => $statement['existing_statement'],
                'existingSource' => $statement['existing_fact_id'] !== null ? ($existing[$statement['existing_fact_id']] ?? null) : null,
                'explanation' => $statement['explanation'],
            ], $statements),
            'replyStatus' => $interpretation->reply_status->value,
            'replyStatusLabel' => $interpretation->reply_status->label(),
            'replyText' => $interpretation->reply_text,
            'replyRecipients' => $interpretation->reply_recipients ?? [],
            'repliedAt' => $interpretation->replied_at?->toIso8601String(),
            'error' => $interpretation->error,
            'processedAt' => $interpretation->processed_at?->toIso8601String(),
        ];
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
                : ['type' => 'document', 'id' => (int) $fact->document_id, 'title' => (string) $fact->document?->title, 'sectionId' => $fact->section_id, 'headingPath' => $fact->section?->heading_path, 'page' => $fact->page_from];
        }

        return $sources;
    }
}
