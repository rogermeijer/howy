<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Tokens spent on one AI call, per account and per step, so cost can be
 * reported per tenant. Written by the AiGateway only.
 *
 * @property int $id
 * @property int $account_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $step
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $cached_input_tokens
 * @property int $output_tokens
 * @property bool $is_batch
 * @property int $estimated_cost_micros
 * @property Carbon|null $created_at
 * @property-read Account $account
 * @property-read Model|null $subject
 */
#[Fillable([
    'subject_type', 'subject_id', 'step', 'provider', 'model', 'input_tokens',
    'cached_input_tokens', 'output_tokens', 'is_batch', 'estimated_cost_micros',
])]
class AiUsageRecord extends Model
{
    use BelongsToAccount;

    public const null UPDATED_AT = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_batch' => 'boolean',
        ];
    }
}
