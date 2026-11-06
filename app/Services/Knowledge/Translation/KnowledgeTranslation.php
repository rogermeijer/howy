<?php

namespace App\Services\Knowledge\Translation;

use App\Enums\FactStatus;
use App\Enums\Locale;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeSummary;
use App\Models\KnowledgeTopic;
use App\Services\Knowledge\Ai\Agents\KnowledgeTranslator;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Extraction\TextLanguage;
use App\Services\Knowledge\Facts\FactSources;
use App\Services\Knowledge\KnowledgeLanguage;
use App\Services\Knowledge\Review\ApprovalQueue;
use App\Services\Knowledge\Review\FactReconciler;
use App\Services\Mail\Interpretation\MailFactWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brings what Howy wrote in another language into the language the knowledge
 * base is built in: facts and summaries, judged per document or mail by their
 * own text, and folders when the language was changed on purpose. What is in
 * it already is left alone; a document's own text is never touched.
 *
 * Afterwards the same claim from two documents is one fact with both
 * sources, and what waits for approval is checked again against the
 * knowledge as it now reads.
 */
class KnowledgeTranslation
{
    public function __construct(
        private readonly AiGateway $ai,
        private readonly FactSources $sources,
        private readonly FactReconciler $reconciler,
        private readonly ApprovalQueue $queue,
    ) {}

    /**
     * @param  bool  $folders  also folder names, descriptions and summaries: they
     *                         are too short to tell their language by
     * @return array{facts: int, summaries: int, folders: int, merged: int, rechecked: int}
     */
    public function run(bool $folders = false): array
    {
        if (! $this->ai->enabled()) {
            return ['facts' => 0, 'summaries' => 0, 'folders' => 0, 'merged' => 0, 'rechecked' => 0];
        }

        $target = KnowledgeLanguage::locale();
        $facts = $this->facts($target);

        return [
            'facts' => $facts,
            'summaries' => $this->summaries($target),
            'folders' => $this->folders($target, $folders),
            'merged' => $this->mergeSame(),
            'rechecked' => $facts > 0 ? $this->recheck() : 0,
        ];
    }

    /**
     * Facts per document and per mail, translated where their own text is in
     * another language. Too little text to tell (a mail of one statement)
     * goes by the language it was searched in.
     */
    private function facts(Locale $target): int
    {
        $groups = KnowledgeFact::query()
            ->where('status', '!=', FactStatus::Expired)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (KnowledgeFact $fact): string => $fact->source_type === 'email' ? "mail:{$fact->source_id}" : 'document:'.($fact->document_id ?? 0));

        $translated = 0;

        foreach ($groups as $facts) {
            $language = TextLanguage::guess($facts->pluck('statement')->implode(' '))
                ?? Locale::forSearchConfiguration((string) $facts->first()?->search_config);

            if ($language === null || $language === $target) {
                continue;
            }

            $translated += $this->translateFacts($facts, $target);
        }

        return $translated;
    }

    /**
     * @param  Collection<int, KnowledgeFact>  $facts
     */
    private function translateFacts(Collection $facts, Locale $target): int
    {
        $subjects = $facts->pluck('subject')->filter()->unique()->values();
        $subjectIds = $subjects->mapWithKeys(fn (string $subject, int $index): array => [$subject => 'S'.($index + 1)]);

        $texts = $this->translate([
            ...$facts->mapWithKeys(fn (KnowledgeFact $fact): array => ['F'.$fact->id => $fact->statement])->all(),
            ...$subjects->mapWithKeys(fn (string $subject): array => [(string) $subjectIds[$subject] => $subject])->all(),
        ], $target);

        $done = [];

        DB::transaction(function () use ($facts, $texts, $subjectIds, $target, &$done): void {
            foreach ($facts as $fact) {
                $statement = $texts['F'.$fact->id] ?? null;

                if ($statement === null) {
                    continue;
                }

                $fact->update([
                    'statement' => $statement,
                    'subject' => $fact->subject === null ? null : mb_strtolower(Str::limit($texts[(string) $subjectIds[$fact->subject]] ?? $fact->subject, 120, '')),
                    'content_hash' => MailFactWriter::hash($statement),
                    'search_config' => $target->searchConfiguration(),
                ]);
                $done[$fact->id] = $fact;
            }

            $this->renameStatements($done);
        });

        $this->embed(array_values($done));

        return count($done);
    }

    /**
     * A mail's statements waiting for approval read as their facts now do.
     *
     * @param  array<int, KnowledgeFact>  $facts
     */
    private function renameStatements(array $facts): void
    {
        $emailIds = array_values(array_unique(array_map(fn (KnowledgeFact $fact): int => $fact->source_id, array_filter($facts, fn (KnowledgeFact $fact): bool => $fact->source_type === 'email'))));

        foreach (EmailInterpretation::query()->whereIn('email_id', $emailIds)->get() as $interpretation) {
            $interpretation->update(['statements' => array_map(
                fn (array $statement): array => isset($statement['fact_id'], $facts[$statement['fact_id']]) ? [...$statement, 'statement' => $facts[$statement['fact_id']]->statement] : $statement,
                $interpretation->statements ?? [],
            )]);
        }
    }

    /**
     * Section and document summaries, per document, where they are in another
     * language. Their input hash stays: they summarise the same text.
     */
    private function summaries(Locale $target): int
    {
        $summaries = KnowledgeSummary::query()->whereIn('summarizable_type', ['document_section', 'document_version'])->get();
        $sections = DocumentSection::query()->whereKey($summaries->where('summarizable_type', 'document_section')->pluck('summarizable_id'))->pluck('document_id', 'id');
        $versions = DocumentVersion::query()->whereKey($summaries->where('summarizable_type', 'document_version')->pluck('summarizable_id'))->pluck('document_id', 'id');

        $translated = 0;

        $byDocument = $summaries->groupBy(fn (KnowledgeSummary $summary): int => (int) ($summary->summarizable_type === 'document_section'
            ? $sections[$summary->summarizable_id] ?? 0
            : $versions[$summary->summarizable_id] ?? 0));

        foreach ($byDocument as $group) {
            $language = TextLanguage::guess($group->pluck('text')->implode(' '));

            if ($language === null || $language === $target) {
                continue;
            }

            $texts = $this->translate($group->mapWithKeys(fn (KnowledgeSummary $summary): array => ['M'.$summary->id => $summary->text])->all(), $target);

            foreach ($group as $summary) {
                if (isset($texts['M'.$summary->id])) {
                    $summary->update(['text' => $texts['M'.$summary->id]]);
                    $translated++;
                }
            }
        }

        return $translated;
    }

    /**
     * Folders: their summaries where they can be told to be in another
     * language, and when asked (the language was changed), every name and
     * description as well. A name that would clash with a sibling's stays.
     */
    private function folders(Locale $target, bool $all): int
    {
        $topics = KnowledgeTopic::query()->orderBy('depth')->get()->filter(fn (KnowledgeTopic $topic): bool => $all
            || ($topic->summary !== null && ($language = TextLanguage::guess($topic->summary)) !== null && $language !== $target));

        if ($topics->isEmpty()) {
            return 0;
        }

        $entries = [];

        foreach ($topics as $topic) {
            if ($all) {
                $entries['N'.$topic->id] = $topic->name;
                $entries['D'.$topic->id] = (string) $topic->description;
            }

            if ($topic->summary !== null) {
                $entries['T'.$topic->id] = $topic->summary;
            }
        }

        $texts = $this->translate(array_filter($entries, fn (string $text): bool => $text !== ''), $target);
        $translated = 0;

        foreach ($topics as $topic) {
            $name = $texts['N'.$topic->id] ?? $topic->name;
            $slug = Str::slug($name);
            $clash = $slug !== $topic->slug && KnowledgeTopic::query()->where('parent_id', $topic->parent_id)->where('slug', $slug)->exists();

            $topic->update([
                'name' => $clash ? $topic->name : Str::limit($name, 120, ''),
                'slug' => $clash ? $topic->slug : $slug,
                'description' => $texts['D'.$topic->id] ?? $topic->description,
                'summary' => $texts['T'.$topic->id] ?? $topic->summary,
            ]);
            $translated++;
        }

        if ($all) {
            $vectors = $this->ai->embed(array_values($topics->map(fn (KnowledgeTopic $topic): string => $topic->name.': '.$topic->description)->all()), 'topics');

            foreach ($topics->values() as $index => $topic) {
                $topic->update(['embedding' => $vectors[$index] ?? $topic->embedding]);
            }
        }

        return $translated;
    }

    /**
     * Facts that now say the same thing word for word are one fact: the
     * oldest keeps standing, the others become its sources.
     */
    private function mergeSame(): int
    {
        $hashes = KnowledgeFact::query()
            ->whereIn('status', [FactStatus::Core, FactStatus::Supplementary])
            ->groupBy('content_hash')
            ->havingRaw('count(*) > 1')
            ->pluck('content_hash');

        $merged = 0;

        foreach ($hashes as $hash) {
            $facts = KnowledgeFact::query()->whereIn('status', [FactStatus::Core, FactStatus::Supplementary])->where('content_hash', $hash)->orderBy('id')->get();
            $known = $facts->shift();

            if ($known === null) {
                continue;
            }

            foreach ($facts as $duplicate) {
                DB::transaction(function () use ($known, $duplicate): void {
                    if ($duplicate->status === FactStatus::Core) {
                        $known->update(['status' => FactStatus::Core]);
                    }

                    $this->sources->confirm($known, $duplicate);
                });
                $merged++;
            }
        }

        return $merged;
    }

    /**
     * Versions waiting for approval are judged again against what the
     * knowledge base now says, in one language.
     */
    private function recheck(): int
    {
        $versions = $this->queue->documents()->get();

        foreach ($versions as $version) {
            KnowledgeFact::query()
                ->where('document_version_id', $version->id)
                ->where('status', FactStatus::Proposed)
                ->update(['review_verdict' => null, 'related_fact_id' => null, 'review_explanation' => null]);

            $this->reconciler->reconcile($version);
        }

        return $versions->count();
    }

    /**
     * @param  array<string, string>  $entries  id => text
     * @return array<string, string> id => translation
     */
    private function translate(array $entries, Locale $target): array
    {
        $translated = [];

        foreach (array_chunk($entries, max(1, (int) config('knowledge.translation.group_size')), preserve_keys: true) as $group) {
            $prompt = KnowledgeLanguage::instruction()."\n\n".implode("\n", array_map(
                fn (string $id, string $text): string => "<entry id=\"{$id}\">{$text}</entry>",
                array_keys($group),
                $group,
            ));

            $response = $this->ai->structured(new KnowledgeTranslator, $prompt, 'translate');

            foreach ((array) ($response['translations'] ?? []) as $item) {
                $id = is_array($item) ? (string) ($item['id'] ?? '') : '';
                $text = is_array($item) ? trim((string) ($item['text'] ?? '')) : '';

                if (isset($group[$id]) && $text !== '') {
                    $translated[$id] = $text;
                }
            }
        }

        return $translated;
    }

    /**
     * @param  list<KnowledgeFact>  $facts
     */
    private function embed(array $facts): void
    {
        $vectors = $this->ai->embed(array_map(fn (KnowledgeFact $fact): string => trim(($fact->subject ? $fact->subject.': ' : '').$fact->statement), $facts), 'embed');

        foreach ($facts as $index => $fact) {
            $fact->update(['embedding' => $vectors[$index] ?? null, 'embedding_model' => config('knowledge.embeddings.model')]);
        }
    }
}
