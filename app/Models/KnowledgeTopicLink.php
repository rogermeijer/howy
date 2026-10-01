<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\KnowledgeOrigin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Files a document, section or fact (later: a mail) into a topic folder.
 *
 * @property int $id
 * @property int $account_id
 * @property int $topic_id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property float $relevance
 * @property KnowledgeOrigin $origin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read KnowledgeTopic $topic
 * @property-read Document|DocumentSection|KnowledgeFact|Email $linkable
 */
#[Fillable(['topic_id', 'linkable_type', 'linkable_id', 'relevance', 'origin'])]
class KnowledgeTopicLink extends Model
{
    use BelongsToAccount;

    /**
     * @return BelongsTo<KnowledgeTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(KnowledgeTopic::class, 'topic_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relevance' => 'float',
            'origin' => KnowledgeOrigin::class,
        ];
    }
}
