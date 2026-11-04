<?php

namespace App\Services\Knowledge\Review;

use App\Enums\FactStatus;
use App\Enums\KnowledgeOrigin;
use App\Enums\ProcessingStatus;
use App\Enums\TopicReview;
use App\Jobs\Knowledge\ProcessDocumentVersion;
use App\Jobs\Knowledge\RefreshTopicSummaries;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeSummary;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Models\User;
use App\Services\Knowledge\Enrichment\SectionEnrichment;
use App\Services\Knowledge\Enrichment\TopicOrganizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * An administrator decides on a processed version before anything of it is
 * searched. Approving publishes it in one go: its chunks answer, its facts
 * count, the version it follows hands over. Rejecting removes everything it
 * brought, but remembers the file, so the same file sent again is recognised
 * and stays out.
 */
class DocumentReview
{
    public function __construct(
        private readonly SectionEnrichment $enrichment,
        private readonly TopicOrganizer $topics,
    ) {}

    /**
     * @param  list<int>  $keepExisting  new facts that contradict a known fact the
     *                                   reviewer keeps instead: these new ones go
     */
    public function approve(DocumentVersion $version, User $reviewer, array $keepExisting = []): void
    {
        if ($version->status !== ProcessingStatus::Review || $version->isApproved()) {
            throw new InvalidArgumentException('This version is not waiting for approval.');
        }

        DB::transaction(function () use ($version, $reviewer, $keepExisting): void {
            $document = $version->document;

            $version->update(['approved_at' => now(), 'approved_by_user_id' => $reviewer->id, 'status' => ProcessingStatus::Ready]);
            $version->makeCurrent();
            $document->update(['current_version_id' => $version->id]);

            $this->enrichment->handOver($version);
            $this->settleConflicts($version, $keepExisting);

            KnowledgeFact::query()
                ->where('document_version_id', $version->id)
                ->where('status', FactStatus::Proposed)
                ->update(['status' => $document->is_core ? FactStatus::Core : FactStatus::Supplementary]);

            $this->markTopicsStale($version);

            // An older draft lost the race; a newer one was compared with what
            // was live before and is compared again with what is live now.
            $drafts = $document->versions()
                ->whereNull('approved_at')
                ->whereKeyNot($version->id)
                ->where('status', '!=', ProcessingStatus::Rejected)
                ->get();

            foreach ($drafts as $other) {
                $other->version_number < $version->version_number
                    ? $this->strip($other, __('Replaced by version :version before it was approved.', ['version' => $version->version_number]))
                    : ProcessDocumentVersion::dispatch($other->id)->afterCommit();
            }

            $document->touch();
        });

        RefreshTopicSummaries::dispatch()->afterCommit();
    }

    public function reject(DocumentVersion $version): void
    {
        if ($version->isApproved() || ! in_array($version->status, [ProcessingStatus::Review, ProcessingStatus::Failed], true)) {
            throw new InvalidArgumentException('This version is not waiting for approval.');
        }

        DB::transaction(fn () => $this->strip($version, null));
    }

    /**
     * Keep a version that was never published out for good: everything
     * derived from it and its file go, the row stays as the memory that this
     * file was turned down, so a copy sent later is not proposed again.
     */
    private function strip(DocumentVersion $version, ?string $reason): void
    {
        $sectionIds = $version->sections()->pluck('id');
        $topicIds = KnowledgeTopicLink::query()
            ->where('linkable_type', 'document_section')
            ->whereIn('linkable_id', $sectionIds)
            ->pluck('topic_id')
            ->unique();

        // Polymorphic, so no cascade reaches these.
        KnowledgeTopicLink::query()->where('linkable_type', 'document_section')->whereIn('linkable_id', $sectionIds)->delete();
        KnowledgeSummary::query()->where('summarizable_type', 'document_section')->whereIn('summarizable_id', $sectionIds)->delete();
        KnowledgeSummary::query()->where('summarizable_type', 'document_version')->where('summarizable_id', $version->id)->delete();
        KnowledgeFact::query()->where('document_version_id', $version->id)->delete();
        KnowledgeChunk::query()->where('source_type', 'document_version')->where('source_id', $version->id)->delete();
        DocumentSection::query()->where('document_version_id', $version->id)->delete();
        DocumentProcessingStep::query()->where('document_version_id', $version->id)->delete();

        $files = array_values(array_filter([$version->path, $version->extracted_path]));
        $disk = $version->disk;

        $version->update(['status' => ProcessingStatus::Rejected, 'extracted_path' => null, 'error' => $reason]);
        $version->document->touch();

        DB::afterCommit(fn () => Storage::disk($disk)->delete($files));

        $this->dropEmptyAiFolders($topicIds);
    }

    /**
     * A new fact that contradicts a known one replaces it, as an approved
     * mail statement does, unless the reviewer keeps the known one: then the
     * new fact goes.
     *
     * @param  list<int>  $keepExisting
     */
    private function settleConflicts(DocumentVersion $version, array $keepExisting): void
    {
        $conflicts = KnowledgeFact::query()
            ->where('document_version_id', $version->id)
            ->where('review_verdict', 'conflict')
            ->whereNotNull('related_fact_id')
            ->get();

        foreach ($conflicts as $fact) {
            if (in_array($fact->id, $keepExisting, true)) {
                $fact->delete();

                continue;
            }

            KnowledgeFact::query()
                ->whereKey($fact->related_fact_id)
                ->where('status', '!=', FactStatus::Expired)
                ->update(['status' => FactStatus::Expired, 'valid_until' => now()->toDateString(), 'superseded_by_id' => $fact->id]);
        }
    }

    /**
     * Folders the AI proposed for this version alone go with it.
     *
     * @param  Collection<array-key, mixed>  $topicIds
     */
    private function dropEmptyAiFolders(Collection $topicIds): void
    {
        KnowledgeTopic::query()
            ->whereKey($topicIds)
            ->where('origin', KnowledgeOrigin::Ai)
            ->where('review_status', TopicReview::New)
            ->whereDoesntHave('children')
            ->whereDoesntHave('links')
            ->delete();
    }

    private function markTopicsStale(DocumentVersion $version): void
    {
        $topicIds = KnowledgeTopicLink::query()
            ->where('linkable_type', 'document_section')
            ->whereIn('linkable_id', $version->sections()->select('id'))
            ->pluck('topic_id')
            ->unique();

        foreach (KnowledgeTopic::query()->whereKey($topicIds)->get() as $topic) {
            $this->topics->markStale($topic);
        }
    }
}
