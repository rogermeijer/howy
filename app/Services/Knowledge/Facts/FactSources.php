<?php

namespace App\Services\Knowledge\Facts;

use App\Enums\FactSourceKind;
use App\Enums\FactStatus;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeFactSource;
use App\Models\KnowledgeTopicLink;
use Illuminate\Database\Eloquent\Collection;

/**
 * Where facts are stated, kept in step with the facts. A fact's own source
 * columns are its primary source, the place it is cited from; every source,
 * the primary one included, is a row in knowledge_fact_sources.
 *
 * A claim found again is one more source of the fact, not a second fact. A
 * fact lives as long as one of its sources does, unless someone removing a
 * document chooses to keep its facts.
 */
class FactSources
{
    /** Facts that count, as opposed to proposed or expired ones. */
    private const array LIVE = [FactStatus::Core, FactStatus::Supplementary];

    /**
     * A new fact's own source, from its primary columns.
     */
    public function recordPrimary(KnowledgeFact $fact): void
    {
        if ($fact->source_type === 'email') {
            $email = Email::query()->find($fact->source_id, ['id', 'subject']);
            $fact->sources()->create(['kind' => FactSourceKind::Mail, 'email_id' => $email?->id, 'title' => (string) $email?->subject]);

            return;
        }

        $fact->sources()->create([
            'kind' => FactSourceKind::Document,
            'document_id' => $fact->document_id,
            'document_version_id' => $fact->document_version_id,
            'section_id' => $fact->section_id,
            'chunk_id' => $fact->chunk_id,
            'page_from' => $fact->page_from,
            'page_to' => $fact->page_to,
            'title' => (string) Document::query()->whereKey($fact->document_id)->value('title'),
        ]);
    }

    /**
     * Whether a fact can take on another's sources: it counts already. One
     * still waiting for approval could be turned down and take them along.
     */
    public function canConfirm(KnowledgeFact $known): bool
    {
        return in_array($known->status, self::LIVE, true);
    }

    /**
     * The same claim found again: where the duplicate is stated becomes one
     * more source of the known fact, and the duplicate goes.
     */
    public function confirm(KnowledgeFact $known, KnowledgeFact $duplicate): void
    {
        foreach ($duplicate->sources()->get() as $source) {
            $already = $known->sources()
                ->where('kind', $source->kind)
                ->when($source->kind === FactSourceKind::Mail, fn ($query) => $query->where('email_id', $source->email_id))
                ->when($source->kind === FactSourceKind::Document, fn ($query) => $query->where('section_id', $source->section_id))
                ->exists();

            if (! $already) {
                $source->update(['knowledge_fact_id' => $known->id]);
            }
        }

        $this->forget($duplicate);
    }

    /**
     * A mail that says what is known: one more source of it. It changes
     * nothing that is known, so it waits for nobody.
     */
    public function confirmByMail(KnowledgeFact $known, Email $email): void
    {
        $known->sources()->firstOrCreate(
            ['kind' => FactSourceKind::Mail, 'email_id' => $email->id],
            ['title' => (string) $email->subject],
        );
    }

    /**
     * A new version is published. What its unchanged sections state moves to
     * them; every other source in the previous version is gone. A fact with
     * no other place left no longer holds and expires, its sources kept as
     * history; one stated elsewhere too stays, cited from there. Exactly what
     * handOverPlan() says beforehand.
     *
     * @param  array<int, DocumentSection>  $unchanged  previous section id => the same section in the new version
     * @param  array<int, array{proposed: KnowledgeFact, from: int, to: DocumentSection}>  $carried  fact id => its word-for-word repeat in a changed section
     * @return array{facts: int, expired: int}
     */
    public function handOver(DocumentVersion $previous, DocumentVersion $version, array $unchanged, array $carried = []): array
    {
        $plan = $this->handOverPlan($previous, $unchanged, $carried);
        $moved = 0;

        foreach ($unchanged as $previousSectionId => $section) {
            KnowledgeFactSource::query()
                ->where('section_id', $previousSectionId)
                ->update(['section_id' => $section->id, 'document_version_id' => $version->id, 'chunk_id' => null]);

            $moved += KnowledgeFact::query()
                ->where('section_id', $previousSectionId)
                ->where('status', '!=', FactStatus::Expired)
                ->update([
                    'section_id' => $section->id,
                    'document_version_id' => $version->id,
                    'source_id' => $version->id,
                    'chunk_id' => null,
                ]);
        }

        foreach ($plan['expire'] as $fact) {
            $fact->update(['status' => FactStatus::Expired, 'valid_until' => now()->toDateString()]);
        }

        foreach ($plan['keep'] as ['fact' => $fact, 'here' => $here, 'source' => $source]) {
            KnowledgeFactSource::query()->whereKey($here->modelKeys())->delete();

            if ($fact->refresh()->document_version_id === $previous->id) {
                $this->promote($fact, $source->refresh());
            }
        }

        // Said word for word in a changed section: the fact carries on there,
        // and its repeat goes.
        foreach ($plan['carry'] as ['fact' => $fact, 'here' => $here, 'proposed' => $proposed, 'from' => $from, 'to' => $to]) {
            $place = ['section_id' => $to->id, 'document_version_id' => $version->id, 'chunk_id' => $proposed->chunk_id, 'page_from' => $proposed->page_from, 'page_to' => $proposed->page_to];

            KnowledgeFactSource::query()->whereKey($here->where('section_id', $from)->modelKeys())->update($place);
            KnowledgeFactSource::query()->whereKey($here->where('section_id', '!=', $from)->modelKeys())->delete();

            if ($fact->refresh()->document_version_id === $previous->id) {
                $fact->update([...$place, 'source_type' => 'document_version', 'source_id' => $version->id, 'document_id' => $version->document_id]);
            }

            $this->forget($proposed);
            $moved++;
        }

        return ['facts' => $moved, 'expired' => count($plan['expire'])];
    }

    /**
     * What publishing a new version would do to the facts of the previous
     * one, without doing it. Facts stated in an unchanged section go along
     * and are not listed; those a changed section says word for word again
     * carry on there (`carry`). Of the rest, those stated nowhere else
     * expire; those stated elsewhere too stay, cited from that other place
     * (`elsewhere` true), or from an unchanged section of this document.
     *
     * @param  array<int, DocumentSection>  $unchanged  previous section id => the same section in the new version
     * @param  array<int, array{proposed: KnowledgeFact, from: int, to: DocumentSection}>  $carried  fact id => its word-for-word repeat in a changed section
     * @return array{expire: list<KnowledgeFact>, keep: list<array{fact: KnowledgeFact, here: Collection<int, KnowledgeFactSource>, source: KnowledgeFactSource, elsewhere: bool}>, carry: list<array{fact: KnowledgeFact, here: Collection<int, KnowledgeFactSource>, proposed: KnowledgeFact, from: int, to: DocumentSection}>}
     */
    public function handOverPlan(DocumentVersion $previous, array $unchanged, array $carried = []): array
    {
        $expire = [];
        $keep = [];
        $carry = [];

        foreach ($this->stillIn('document_version_id', $previous->id) as [$fact, $here, $elsewhere]) {
            [$moving, $staying] = $here->partition(fn (KnowledgeFactSource $source): bool => isset($unchanged[(int) $source->section_id]));

            if ($moving->isNotEmpty()) {
                if ($staying->isNotEmpty()) {
                    $keep[] = ['fact' => $fact, 'here' => $staying->values(), 'source' => $moving->first(), 'elsewhere' => false];
                }

                continue;
            }

            if (isset($carried[$fact->id])) {
                $carry[] = ['fact' => $fact, 'here' => $here, ...$carried[$fact->id]];

                continue;
            }

            if ($elsewhere->isEmpty()) {
                $expire[] = $fact;

                continue;
            }

            $keep[] = ['fact' => $fact, 'here' => $here, 'source' => $elsewhere->first(), 'elsewhere' => true];
        }

        return ['expire' => $expire, 'keep' => $keep, 'carry' => $carry];
    }

    /**
     * What removing a document does to the facts it states: how many are
     * stated only there, and how many somewhere else too.
     *
     * @return array{only: int, elsewhere: int}
     */
    public function impact(Document $document): array
    {
        $only = 0;
        $elsewhere = 0;

        foreach ($this->stillIn('document_id', $document->id) as [$fact, , $other]) {
            if (in_array($fact->status, self::LIVE, true)) {
                $other->isEmpty() ? $only++ : $elsewhere++;
            }
        }

        return ['only' => $only, 'elsewhere' => $elsewhere];
    }

    /**
     * A document is being removed. A fact stated somewhere else too stays,
     * cited from there. A fact stated only here goes when the remover says
     * so, and otherwise stays, naming the removed document as its source.
     * What was not knowledge yet (proposed) or no longer is (expired) goes
     * with the document either way.
     *
     * @return array{kept: int, removed: int}
     */
    public function release(Document $document, bool $keepFacts): array
    {
        $kept = 0;
        $removed = 0;

        foreach ($this->stillIn('document_id', $document->id, withExpired: true) as [$fact, $here, $elsewhere]) {
            if ($elsewhere->isNotEmpty()) {
                KnowledgeFactSource::query()->whereKey($here->modelKeys())->delete();

                if ($fact->document_id === $document->id) {
                    $this->promote($fact, $elsewhere->first());
                }

                $kept++;

                continue;
            }

            if (! $keepFacts || ! in_array($fact->status, self::LIVE, true)) {
                $this->forget($fact);
                $removed++;

                continue;
            }

            // Kept with its sources as they are: once the document is gone,
            // they name it without pointing at it.
            $kept++;
        }

        return ['kept' => $kept, 'removed' => $removed];
    }

    /**
     * Cite a fact from another of its sources.
     */
    public function promote(KnowledgeFact $fact, KnowledgeFactSource $source): void
    {
        $fact->update($source->kind === FactSourceKind::Mail ? [
            'source_type' => 'email',
            'source_id' => (int) $source->email_id,
            'document_id' => null,
            'document_version_id' => null,
            'section_id' => null,
            'chunk_id' => null,
            'page_from' => null,
            'page_to' => null,
        ] : [
            'source_type' => 'document_version',
            'source_id' => $source->document_version_id ?? $fact->source_id,
            'document_id' => $source->document_id,
            'document_version_id' => $source->document_version_id,
            'section_id' => $source->section_id,
            'chunk_id' => $source->chunk_id,
            'page_from' => $source->page_from,
            'page_to' => $source->page_to,
        ]);
    }

    /**
     * Facts stated in the given place, each with its sources there and its
     * live sources elsewhere. Expired ones are history and left out, unless
     * the place itself goes.
     *
     * @param  'document_id'|'document_version_id'  $column
     * @return list<array{0: KnowledgeFact, 1: Collection<int, KnowledgeFactSource>, 2: Collection<int, KnowledgeFactSource>}>
     */
    private function stillIn(string $column, int $id, bool $withExpired = false): array
    {
        $facts = KnowledgeFact::query()
            ->with('sources')
            ->when(! $withExpired, fn ($query) => $query->where('status', '!=', FactStatus::Expired))
            ->where(fn ($query) => $query
                ->where($column, $id)
                ->orWhereHas('sources', fn ($sources) => $sources->where($column, $id)))
            ->get();

        return array_values($facts->map(function (KnowledgeFact $fact) use ($column, $id): array {
            [$here, $other] = $fact->sources->partition(fn (KnowledgeFactSource $source): bool => $source->{$column} === $id);

            return [$fact, $here->values(), $other->filter(fn (KnowledgeFactSource $source): bool => $source->isLive())->values()];
        })->all());
    }

    /**
     * A fact goes, with what links to it.
     */
    private function forget(KnowledgeFact $fact): void
    {
        KnowledgeTopicLink::query()->where('linkable_type', 'knowledge_fact')->where('linkable_id', $fact->id)->delete();
        $fact->delete();
    }
}
