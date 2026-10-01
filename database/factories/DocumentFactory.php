<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Personeelshandboek', 'Werkwijze warmtepomp', 'Verlofregeling', 'Handleiding planning']),
            'type' => DocumentType::Handbook,
            'is_core' => true,
            'language' => Locale::Dutch,
        ];
    }

    /**
     * A document with one processed-or-not version, set as its current version.
     */
    public function withVersion(?callable $configure = null): static
    {
        return $this->afterCreating(function (Document $document) use ($configure): void {
            $factory = DocumentVersion::factory()->for($document);
            $version = ($configure ? $configure($factory) : $factory)->create();

            $document->update(['current_version_id' => $version->id]);
        });
    }
}
