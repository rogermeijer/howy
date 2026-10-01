<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step of processing a document version, with its outcome. Token usage is
 * recorded separately in ai_usage_records.
 *
 * @property int $id
 * @property int $account_id
 * @property int $document_version_id
 * @property ProcessingStep $step
 * @property StepStatus $status
 * @property int $attempts
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 * @property string|null $provider_batch_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read DocumentVersion $version
 */
#[Fillable([
    'document_version_id', 'step', 'status', 'attempts', 'started_at', 'finished_at',
    'error', 'provider_batch_id', 'meta',
])]
class DocumentProcessingStep extends Model
{
    use BelongsToAccount;

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => ProcessingStep::class,
            'status' => StepStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
