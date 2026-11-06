<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Puts what Howy wrote earlier (facts, summaries, folder names) into the
 * language the knowledge base is built in, item by item, meaning for meaning.
 */
#[Strict]
class KnowledgeTranslator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You translate entries of a company knowledge base into the language the request names.
        Each entry has an id; return every id with its translation. Translate faithfully: the
        same claim, nothing added or left out, the same register. Keep names of people,
        companies, products and systems, amounts, dates, codes and quoted terms as they are.
        A short entry (a folder name) stays short. An entry already in that language comes
        back unchanged.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'translations' => $schema->array()->items(
                $schema->object([
                    'id' => $schema->string()->required(),
                    'text' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
