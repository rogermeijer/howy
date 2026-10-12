<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one AI call sent and received. Customer text, so encrypted at rest and
 * pruned after knowledge.ai_log.payload_retention_days; the usage record keeps
 * the tokens and cost for good. Written by the AiGateway only.
 *
 * @property int $id
 * @property int $account_id
 * @property int $ai_usage_record_id
 * @property string|null $instructions
 * @property string|null $input
 * @property string|null $output
 * @property list<array{type: string, name: string|null, mime_type: string|null}>|null $attachments
 * @property Carbon|null $created_at
 * @property-read Account $account
 * @property-read AiUsageRecord $record
 */
#[Fillable(['ai_usage_record_id', 'instructions', 'input', 'output', 'attachments'])]
class AiCallPayload extends Model
{
    use BelongsToAccount;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<AiUsageRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(AiUsageRecord::class, 'ai_usage_record_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'instructions' => 'encrypted',
            'input' => 'encrypted',
            'output' => 'encrypted',
            'attachments' => 'array',
        ];
    }
}
