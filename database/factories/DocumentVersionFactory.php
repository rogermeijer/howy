<?php

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hash = hash('sha256', fake()->unique()->uuid());

        return [
            'document_id' => Document::factory(),
            'version_number' => 1,
            'original_filename' => 'handboek.pdf',
            'mime_type' => DocumentVersion::PDF,
            'size_bytes' => 120_000,
            'disk' => 'knowledge',
            'path' => "test/{$hash}.pdf",
            'sha256' => $hash,
            'page_count' => 12,
            'status' => ProcessingStatus::Queued,
        ];
    }

    public function status(ProcessingStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
