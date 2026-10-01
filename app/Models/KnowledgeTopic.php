<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\KnowledgeOrigin;
use App\Enums\TopicKind;
use App\Enums\TopicReview;
use Database\Factories\KnowledgeTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A folder in the knowledge base: a theme, project or relation, at most three
 * levels deep. Content links to the most specific folder; a folder shows its
 * subfolders' content too.
 *
 * `path` is an ltree of ids ("12.45.88"), so a whole subtree is one indexed
 * query (`path <@ '12.45'`) and renaming never touches it.
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $parent_id
 * @property int $depth
 * @property string $path
 * @property TopicKind $kind
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $summary
 * @property bool $summary_stale
 * @property KnowledgeOrigin $origin
 * @property TopicReview $review_status
 * @property list<float>|null $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read KnowledgeTopic|null $parent
 * @property-read Collection<int, KnowledgeTopic> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, KnowledgeTopicLink> $links
 * @property-read int|null $links_count
 */
#[Fillable([
    'parent_id', 'depth', 'path', 'kind', 'name', 'slug', 'description', 'summary',
    'summary_stale', 'origin', 'review_status', 'embedding',
])]
#[Hidden(['embedding'])]
class KnowledgeTopic extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<KnowledgeTopicFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (KnowledgeTopic $topic): void {
            $topic->slug = $topic->slug ?: Str::slug($topic->name);
            $topic->depth = $topic->parent_id === null
                ? 1
                : $topic->parent()->firstOrFail()->depth + 1;
        });

        // The path needs the id, which only exists after the insert.
        static::created(function (KnowledgeTopic $topic): void {
            $topic->path = $topic->parent_id === null
                ? (string) $topic->id
                : $topic->parent()->firstOrFail()->path.'.'.$topic->id;
            $topic->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<KnowledgeTopic, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<KnowledgeTopic, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return HasMany<KnowledgeTopicLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(KnowledgeTopicLink::class, 'topic_id');
    }

    /**
     * This topic and everything below it.
     *
     * @param  Builder<KnowledgeTopic>  $query
     * @return Builder<KnowledgeTopic>
     */
    public function scopeSubtreeOf(Builder $query, KnowledgeTopic $topic): Builder
    {
        return $query->whereRaw('path <@ ?::ltree', [$topic->path]);
    }

    /**
     * The topics above this one, root first.
     *
     * @return Collection<int, KnowledgeTopic>
     */
    public function ancestors(): Collection
    {
        return self::query()
            ->whereRaw('path @> ?::ltree', [$this->path])
            ->whereKeyNot($this->id)
            ->orderBy('depth')
            ->get();
    }

    /**
     * How many levels this topic's subtree spans, itself included.
     */
    public function subtreeHeight(): int
    {
        $deepest = self::query()->subtreeOf($this)->max('depth');

        return (is_numeric($deepest) ? (int) $deepest : $this->depth) - $this->depth + 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TopicKind::class,
            'origin' => KnowledgeOrigin::class,
            'review_status' => TopicReview::class,
            'summary_stale' => 'boolean',
            'embedding' => AsVector::class,
        ];
    }
}
