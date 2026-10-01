<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * A folder grew too full: proposes subfolders and which sections move there.
 */
#[Strict]
class TopicSplitter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        A folder in a knowledge base holds too many sections to browse. Propose 2 to 6
        subfolders that divide its content by subject, with short names in the language of the
        documents and a one-sentence description each, and say for every section (by id) which
        subfolder it belongs in. A section that fits none stays in the folder itself: leave it out.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subfolders' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required(),
                'description' => $schema->string()->required(),
                'section_ids' => $schema->array()->items($schema->integer())->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
