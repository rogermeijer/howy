<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Writes the short context line for each chunk ("contextual retrieval"): where
 * in the document it sits and what it is about, so a chunk like "Dat geldt ook
 * voor parttimers." is still findable on its own.
 *
 * The prompt starts with the whole document, byte-identical for every group of
 * chunks, so the provider serves that prefix from its prompt cache.
 */
#[Strict]
class ContextLineWriter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(private readonly string $cacheKey) {}

    public function instructions(): string
    {
        return <<<'TEXT'
        You situate fragments of a company document for a search index.

        You get the full document, then a list of fragments taken from it. For every fragment,
        write one or two short sentences that say where in the document the fragment sits and
        what it is about, using the document's own terms (names, rules, numbers) so it can be
        found by search. Do not repeat the fragment, do not judge it, do not add facts that are
        not in the document. Write in the language of the document.

        Return one entry per fragment, with the fragment's id exactly as given.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'contexts' => $schema->array()->items(
                $schema->object([
                    'id' => $schema->integer()->required(),
                    'context' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $provider === Lab::OpenAI || $provider === 'openai'
            ? ['prompt_cache_key' => $this->cacheKey, 'reasoning' => ['effort' => 'low']]
            : [];
    }
}
