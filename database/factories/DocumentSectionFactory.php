<?php

namespace Database\Factories;

use App\Enums\SectionChange;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<DocumentSection>
 */
class DocumentSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $heading = fake()->randomElement(['Verlof', 'Ziekmelding', 'Werktijden', 'Onkosten']);

        return [
            'document_version_id' => DocumentVersion::factory(),
            'document_id' => fn (array $attributes) => DocumentVersion::query()->whereKey($attributes['document_version_id'])->value('document_id'),
            'level' => 1,
            'ordinal' => fake()->unique()->numberBetween(1, 100000),
            'heading' => $heading,
            'heading_path' => $heading,
            'page_from' => 1,
            'page_to' => 2,
            'content_hash' => hash('sha256', fake()->uuid()),
            'token_count' => 300,
            'change_type' => SectionChange::Added,
        ];
    }
}
