<?php

namespace Database\Factories;

use App\Enums\FactStatus;
use App\Models\DocumentSection;
use App\Models\KnowledgeFact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<KnowledgeFact>
 */
class KnowledgeFactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statement = fake()->randomElement([
            'Medewerkers hebben recht op 25 vakantiedagen per jaar.',
            'Ziekmelden gebeurt vóór 09:00 telefonisch bij de leidinggevende.',
            'In het weekend wordt niet gewerkt.',
        ]);

        return [
            'section_id' => DocumentSection::factory(),
            'source_type' => 'document_version',
            'source_id' => fn (array $attributes) => DocumentSection::query()->whereKey($attributes['section_id'])->value('document_version_id'),
            'document_version_id' => fn (array $attributes) => $attributes['source_id'],
            'document_id' => fn (array $attributes) => DocumentSection::query()->whereKey($attributes['section_id'])->value('document_id'),
            'statement' => $statement,
            'subject' => 'verlof',
            'status' => FactStatus::Core,
            'confidence' => 0.9,
            'page_from' => 1,
            'page_to' => 1,
            'content_hash' => hash('sha256', $statement.fake()->uuid()),
            'search_config' => 'dutch',
        ];
    }
}
