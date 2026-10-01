<?php

namespace App\Services\Knowledge;

use App\Enums\ProcessingStep;
use App\Enums\StepStatus;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentVersion;
use Throwable;

/**
 * Records one pipeline step for the processing timeline: when it ran, how
 * often, what it produced and why it failed.
 */
class StepRecorder
{
    /**
     * @param  callable(DocumentProcessingStep): (array<string, mixed>|null)  $work  returns meta to keep
     */
    public function run(DocumentVersion $version, ProcessingStep $step, callable $work): void
    {
        $record = DocumentProcessingStep::query()->firstOrNew([
            'document_version_id' => $version->id,
            'step' => $step,
        ]);

        $record->fill([
            'status' => StepStatus::Running,
            'attempts' => $record->exists ? $record->attempts + 1 : 1,
            'started_at' => now(),
            'finished_at' => null,
            'error' => null,
        ])->save();

        try {
            $meta = $work($record);
        } catch (Throwable $e) {
            $record->update(['status' => StepStatus::Failed, 'finished_at' => now(), 'error' => $e->getMessage()]);

            throw $e;
        }

        $record->update([
            'status' => StepStatus::Succeeded,
            'finished_at' => now(),
            'meta' => $meta === null ? $record->meta : [...($record->meta ?? []), ...$meta],
        ]);
    }

    public function skip(DocumentVersion $version, ProcessingStep $step, string $reason): void
    {
        DocumentProcessingStep::query()->updateOrCreate(
            ['document_version_id' => $version->id, 'step' => $step],
            ['status' => StepStatus::Skipped, 'started_at' => now(), 'finished_at' => now(), 'meta' => ['reason' => $reason]],
        );
    }
}
