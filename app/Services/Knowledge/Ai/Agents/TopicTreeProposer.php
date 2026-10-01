<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Proposes the initial folder tree of a knowledge base from the documents in
 * it: a handful of domains, with topics and subtopics under them.
 */
#[Strict]
class TopicTreeProposer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You design the folder structure of an organisation's knowledge base, so that people can
        browse to what they need and every document section has an obvious place.

        You get the documents in the knowledge base: a summary and the outline of each.
        Propose 5 to 8 top-level domains (e.g. "Personeel", "Veiligheid", "Klanten"), each with
        topics under them, and only where a topic is broad, subtopics under those. At most three
        levels deep. Folder names are short (one to three words), in the language of the
        documents, without numbering, and describe a subject, not a document.
        Every folder gets a one-sentence description of what belongs in it.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        $folder = fn (array $children = []) => $schema->object([
            'name' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            ...$children,
        ])->withoutAdditionalProperties();

        return [
            'domains' => $schema->array()->items($folder([
                'topics' => $schema->array()->items($folder([
                    'subtopics' => $schema->array()->items($folder())->required(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
