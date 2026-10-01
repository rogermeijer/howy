<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A generated summary of a document version or a section.
 *
 * @property int $id
 * @property int $account_id
 * @property string $summarizable_type
 * @property int $summarizable_id
 * @property string $text
 * @property int $token_count
 * @property string $input_hash
 * @property string $model
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read DocumentVersion|DocumentSection $summarizable
 */
#[Fillable(['summarizable_type', 'summarizable_id', 'text', 'token_count', 'input_hash', 'model'])]
class KnowledgeSummary extends Model
{
    use BelongsToAccount;

    /**
     * @return MorphTo<Model, $this>
     */
    public function summarizable(): MorphTo
    {
        return $this->morphTo();
    }
}
