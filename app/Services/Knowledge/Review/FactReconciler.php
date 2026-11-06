<?php

namespace App\Services\Knowledge\Review;

use App\Enums\FactStatus;
use App\Enums\Locale;
use App\Models\DocumentVersion;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Ai\Agents\FactConflictChecker;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Enrichment\SectionEnrichment;
use App\Services\Knowledge\KnowledgeLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Holds new claims against the facts already known, before anything is
 * added: the same claim is a duplicate, a claim that cannot hold beside a
 * known fact is a conflict, the rest is new.
 *
 * Known means published and pending alike: a claim that waits for review in
 * another mail or document is not proposed a second time. Mail and documents
 * share the check; documents find their candidates by the embedding each new
 * fact already has, so the check costs no extra embedding.
 *
 * @phpstan-type Claim array{statement: string, valid_from: string|null, candidates: list<KnowledgeFact>}
 * @phpstan-type Judgement array{verdict: 'new'|'duplicate'|'conflict', existing: KnowledgeFact|null, explanation: string|null}
 */
class FactReconciler
{
    public function __construct(
        private readonly AiGateway $ai,
        private readonly SectionEnrichment $enrichment,
    ) {}

    /**
     * Ask the conflict check about claims that have candidates, in groups.
     *
     * @param  array<string, Claim>  $open  keyed by an id the model refers back to
     * @return array<string, Judgement>
     */
    public function check(array $open, string $locale, Model $subject, string $step): array
    {
        $judged = [];

        foreach (array_chunk($open, max(1, (int) config('knowledge.review.conflict_group_size')), true) as $group) {
            $response = $this->ai->structured(new FactConflictChecker, $this->prompt($group, $locale), $step, $subject);

            /** @var list<array{id?: string, verdict?: string, existing_fact_id?: int|null, explanation?: string}> $verdicts */
            $verdicts = is_array($response['verdicts'] ?? null) ? $response['verdicts'] : [];
            $byId = [];

            foreach ($verdicts as $verdict) {
                $byId[(string) ($verdict['id'] ?? '')] = $verdict;
            }

            foreach ($group as $id => $claim) {
                $verdict = $byId[$id] ?? [];
                $existing = collect($claim['candidates'])->firstWhere('id', (int) ($verdict['existing_fact_id'] ?? 0));
                $explanation = trim((string) ($verdict['explanation'] ?? ''));

                // A verdict that points at no fact we offered cannot be acted on.
                $judged[$id] = match ($existing === null ? 'new' : ($verdict['verdict'] ?? 'new')) {
                    'duplicate' => ['verdict' => 'duplicate', 'existing' => $existing, 'explanation' => null],
                    'contradicts' => ['verdict' => 'conflict', 'existing' => $existing, 'explanation' => $explanation !== '' ? $explanation : null],
                    default => ['verdict' => 'new', 'existing' => null, 'explanation' => null],
                };
            }
        }

        return $judged;
    }

    /**
     * Judge the new facts of a version that waits for review, against every
     * other document and mail. Its own document's earlier version is not a
     * conflict: approving the version replaces it.
     *
     * @return array{checked: int, new: int, duplicate: int, conflict: int}
     */
    public function reconcile(DocumentVersion $version): array
    {
        // What a changed section repeats word for word carries on as it is:
        // nothing to check.
        $repeats = array_map(fn (array $carried): int => $carried['proposed']->id, array_values($this->enrichment->carried($version)));

        $facts = KnowledgeFact::query()
            ->where('document_version_id', $version->id)
            ->where('status', FactStatus::Proposed)
            ->whereNull('review_verdict')
            ->whereKeyNot($repeats)
            ->orderBy('id')
            ->limit((int) config('knowledge.review.max_facts'))
            ->get();

        $counts = ['checked' => $facts->count(), 'new' => 0, 'duplicate' => 0, 'conflict' => 0];
        $open = [];

        foreach ($facts as $fact) {
            $known = $this->otherSources(KnowledgeFact::query(), $version->document_id)
                ->where('content_hash', $fact->content_hash)
                ->where('status', '!=', FactStatus::Expired)
                ->first();

            if ($known !== null) {
                $fact->update(['review_verdict' => 'duplicate', 'related_fact_id' => $known->id]);
                $counts['duplicate']++;

                continue;
            }

            $candidates = $this->nearest($fact, $version->document_id);

            if ($candidates === []) {
                $fact->update(['review_verdict' => 'new']);
                $counts['new']++;

                continue;
            }

            $open['N'.$fact->id] = ['statement' => $fact->statement, 'valid_from' => $fact->valid_from?->toDateString(), 'candidates' => $candidates];
        }

        // Explained to the reviewer, in the language the knowledge base is in.
        $locale = KnowledgeLanguage::locale()->value;

        foreach ($this->check($open, $locale, $version, 'fact_conflicts') as $id => $judgement) {
            KnowledgeFact::query()->whereKey((int) substr($id, 1))->update([
                'review_verdict' => $judgement['verdict'],
                'related_fact_id' => $judgement['existing']?->id,
                'review_explanation' => $judgement['explanation'],
            ]);
            $counts[$judgement['verdict']]++;
        }

        return $counts;
    }

    /**
     * The known facts closest in meaning, by the embedding the fact has.
     *
     * @return list<KnowledgeFact>
     */
    private function nearest(KnowledgeFact $fact, int $documentId): array
    {
        if ($fact->embedding === null) {
            return [];
        }

        $literal = '['.implode(',', $fact->embedding).']';

        return array_values($this->otherSources(KnowledgeFact::query(), $documentId)
            ->whereIn('status', [FactStatus::Core, FactStatus::Supplementary, FactStatus::Proposed])
            ->whereNotNull('embedding')
            ->whereRaw('knowledge_facts.embedding <=> ?::vector <= ?', [$literal, 1 - (float) config('knowledge.review.min_similarity')])
            ->orderByRaw('knowledge_facts.embedding <=> ?::vector', [$literal])
            ->limit((int) config('knowledge.review.conflict_candidates'))
            ->get()
            ->all());
    }

    /**
     * @param  Builder<KnowledgeFact>  $query
     * @return Builder<KnowledgeFact>
     */
    private function otherSources($query, int $documentId)
    {
        return $query->where(fn ($query) => $query->whereNull('document_id')->orWhere('document_id', '!=', $documentId));
    }

    /**
     * @param  array<string, Claim>  $open
     */
    private function prompt(array $open, string $locale): string
    {
        $blocks = [];

        foreach ($open as $id => $claim) {
            $facts = array_map(
                fn (KnowledgeFact $fact): string => "  <fact id=\"{$fact->id}\"".($fact->valid_from ? " valid_from=\"{$fact->valid_from->toDateString()}\"" : '').">{$fact->statement}</fact>",
                $claim['candidates'],
            );

            $blocks[] = "<statement id=\"{$id}\"".($claim['valid_from'] ? " valid_from=\"{$claim['valid_from']}\"" : '').">\n  {$claim['statement']}\n".implode("\n", $facts)."\n</statement>";
        }

        return 'Explain in: '.(Locale::tryFrom($locale)?->label() ?? $locale)."\n\n".implode("\n\n", $blocks);
    }
}
