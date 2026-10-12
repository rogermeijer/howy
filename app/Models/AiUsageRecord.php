<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One AI call: tokens, cost, latency and outcome, per account and per step, so
 * cost can be reported per tenant, thread and call. Failed calls are recorded
 * too, with no tokens. Kept for good; what was sent lives in AiCallPayload and
 * is pruned. Written by the AiGateway only.
 *
 * @property int $id
 * @property int $account_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $thread_key
 * @property string $step
 * @property string|null $agent
 * @property string $provider
 * @property string $model
 * @property string $status
 * @property string|null $error
 * @property int|null $latency_ms
 * @property int|null $input_count
 * @property int $input_tokens
 * @property int $cached_input_tokens
 * @property int $output_tokens
 * @property bool $is_batch
 * @property int $estimated_cost_micros
 * @property Carbon|null $created_at
 * @property-read Account $account
 * @property-read Model|null $subject
 * @property-read AiCallPayload|null $payload
 */
#[Fillable([
    'subject_type', 'subject_id', 'thread_key', 'step', 'agent', 'provider', 'model', 'status', 'error',
    'latency_ms', 'input_count', 'input_tokens', 'cached_input_tokens', 'output_tokens', 'is_batch',
    'estimated_cost_micros',
])]
class AiUsageRecord extends Model
{
    use BelongsToAccount;

    public const null UPDATED_AT = null;

    public const string SUCCEEDED = 'succeeded';

    public const string FAILED = 'failed';

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasOne<AiCallPayload, $this>
     */
    public function payload(): HasOne
    {
        return $this->hasOne(AiCallPayload::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_batch' => 'boolean',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_micros' => 'integer',
            'latency_ms' => 'integer',
            'input_count' => 'integer',
        ];
    }
}
