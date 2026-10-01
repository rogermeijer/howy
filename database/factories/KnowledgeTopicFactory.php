<?php

namespace Database\Factories;

use App\Enums\KnowledgeOrigin;
use App\Enums\TopicKind;
use App\Enums\TopicReview;
use App\Models\KnowledgeTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 * depth and path are derived from the parent when the topic is created.
 *
 * @extends Factory<KnowledgeTopic>
 */
class KnowledgeTopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => rtrim(fake()->unique()->sentence(2), '.'),
            'kind' => TopicKind::Theme,
            'origin' => KnowledgeOrigin::Ai,
            'review_status' => TopicReview::Approved,
            'summary_stale' => false,
        ];
    }

    public function childOf(KnowledgeTopic $parent): static
    {
        return $this->state(fn (array $attributes) => ['parent_id' => $parent->id]);
    }
}
