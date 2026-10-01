<?php

namespace Database\Factories;

use App\Enums\ChunkKind;
use App\Models\DocumentSection;
use App\Models\KnowledgeChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<KnowledgeChunk>
 */
class KnowledgeChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = fake()->paragraph(4);

        return [
            'section_id' => DocumentSection::factory(),
            'source_type' => 'document_version',
            'source_id' => fn (array $attributes) => DocumentSection::query()->whereKey($attributes['section_id'])->value('document_version_id'),
            'document_id' => fn (array $attributes) => DocumentSection::query()->whereKey($attributes['section_id'])->value('document_id'),
            'ordinal' => fake()->unique()->numberBetween(1, 100000),
            'kind' => ChunkKind::Text,
            'content' => $content,
            'page_from' => 1,
            'page_to' => 1,
            'token_count' => 120,
            'content_hash' => hash('sha256', $content),
            'is_current' => true,
            'search_config' => 'dutch',
        ];
    }
}
