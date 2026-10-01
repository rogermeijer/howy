<?php

namespace App\Services\Knowledge\Topics;

use App\Enums\KnowledgeOrigin;
use App\Enums\TopicReview;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Services\Knowledge\Enrichment\TopicOrganizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Curating the folder tree: rename, approve, move, merge and delete, keeping
 * every ltree path and depth consistent and the tree at most
 * topics.max_depth levels deep. Whatever an admin does here is marked manual,
 * so AI never undoes it.
 */
class TopicTree
{
    public function __construct(private readonly TopicOrganizer $organizer) {}

    public function create(string $name, ?string $description, ?KnowledgeTopic $parent): KnowledgeTopic
    {
        if ($parent !== null && $parent->depth >= $this->maxDepth()) {
            throw ValidationException::withMessages(['parent_id' => __('Folders go at most :depth levels deep.', ['depth' => $this->maxDepth()])]);
        }

        [$topic] = $this->organizer->findOrCreateExact($name, $description, $parent);

        return $topic;
    }

    /**
     * @param  array{name?: string, description?: string|null, kind?: string}  $attributes
     */
    public function update(KnowledgeTopic $topic, array $attributes): void
    {
        $topic->fill($attributes);

        if ($topic->isDirty('name')) {
            $topic->slug = Str::slug($topic->name);
        }

        $topic->fill(['origin' => KnowledgeOrigin::Manual, 'review_status' => TopicReview::Approved])->save();
    }

    public function approve(KnowledgeTopic $topic): void
    {
        $topic->update(['review_status' => TopicReview::Approved]);
    }

    public function move(KnowledgeTopic $topic, ?KnowledgeTopic $parent): void
    {
        if ($parent !== null && ($parent->id === $topic->id || str_starts_with($parent->path.'.', $topic->path.'.'))) {
            throw ValidationException::withMessages(['parent_id' => __('A folder cannot move into itself.')]);
        }

        $newDepth = $parent === null ? 1 : $parent->depth + 1;

        if ($newDepth + $topic->subtreeHeight() - 1 > $this->maxDepth()) {
            throw ValidationException::withMessages(['parent_id' => __('Folders go at most :depth levels deep.', ['depth' => $this->maxDepth()])]);
        }

        DB::transaction(function () use ($topic, $parent, $newDepth): void {
            $oldPath = $topic->path;
            $newPath = $parent === null ? (string) $topic->id : $parent->path.'.'.$topic->id;
            $delta = $newDepth - $topic->depth;

            foreach (KnowledgeTopic::query()->subtreeOf($topic)->orderBy('depth')->get() as $node) {
                $node->forceFill([
                    'path' => $newPath.substr($node->path, strlen($oldPath)),
                    'depth' => $node->depth + $delta,
                ]);

                if ($node->id === $topic->id) {
                    $node->parent_id = $parent?->id;
                    $node->origin = KnowledgeOrigin::Manual;
                    $node->review_status = TopicReview::Approved;
                }

                $node->save();
            }
        });

        $this->organizer->markStale($topic->refresh());

        if ($parent !== null) {
            $this->organizer->markStale($parent);
        }
    }

    /**
     * Fold $source into $target: its links and subfolders move over, then it
     * is removed.
     */
    public function merge(KnowledgeTopic $source, KnowledgeTopic $target): void
    {
        if ($source->id === $target->id || str_starts_with($target->path.'.', $source->path.'.')) {
            throw ValidationException::withMessages(['target_id' => __('A folder cannot be merged into itself or one of its subfolders.')]);
        }

        DB::transaction(function () use ($source, $target): void {
            $this->moveLinks($source, $target);

            foreach ($source->children()->get() as $child) {
                $this->move($child, $target);
            }

            $source->delete();
            $target->update(['origin' => KnowledgeOrigin::Manual, 'review_status' => TopicReview::Approved]);
        });

        $this->organizer->markStale($target->refresh());
    }

    /**
     * Remove a folder. Its content and subfolders go to the folder above it;
     * a top-level folder's subfolders become top-level folders.
     */
    public function delete(KnowledgeTopic $topic): void
    {
        DB::transaction(function () use ($topic): void {
            $parent = $topic->parent;

            if ($parent !== null) {
                $this->moveLinks($topic, $parent);
            }

            foreach ($topic->children()->get() as $child) {
                $this->move($child, $parent);
            }

            $topic->delete();

            if ($parent !== null) {
                $this->organizer->markStale($parent);
            }
        });
    }

    private function moveLinks(KnowledgeTopic $from, KnowledgeTopic $to): void
    {
        foreach (KnowledgeTopicLink::query()->where('topic_id', $from->id)->get() as $link) {
            KnowledgeTopicLink::query()->firstOrCreate(
                ['topic_id' => $to->id, 'linkable_type' => $link->linkable_type, 'linkable_id' => $link->linkable_id],
                ['origin' => KnowledgeOrigin::Manual, 'relevance' => $link->relevance],
            );
        }

        KnowledgeTopicLink::query()->where('topic_id', $from->id)->delete();
    }

    private function maxDepth(): int
    {
        return (int) config('knowledge.topics.max_depth');
    }
}
