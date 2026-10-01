<?php

namespace Tests\Concerns;

use App\Services\Knowledge\Ai\Agents\ContextLineWriter;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\EmbeddingsResponse;

/**
 * A fake AI provider for knowledge base tests: deterministic embeddings (equal
 * text, equal vector; texts sharing words point the same way) and context
 * lines that echo the fragment ids they were asked for. Nothing leaves the
 * test process.
 */
trait FakesKnowledgeAi
{
    protected function fakeKnowledgeAi(): void
    {
        config(['ai.providers.openai.key' => 'test-key']);

        Embeddings::fake(fn (EmbeddingsPrompt $prompt) => new EmbeddingsResponse(
            array_map(fn (string $text): array => $this->fakeVector($text, (int) $prompt->dimensions), $prompt->inputs),
            new Usage(10 * count($prompt->inputs)),
            new Meta('openai', (string) $prompt->model),
        ))->preventStrayEmbeddings();

        ContextLineWriter::fake(function (string $prompt): array {
            preg_match_all('/<fragment id="(\d+)"/', $prompt, $ids);

            return ['contexts' => array_map(fn (string $id): array => ['id' => (int) $id, 'context' => "Context voor fragment {$id}."], $ids[1])];
        })->preventStrayPrompts();
    }

    /**
     * A bag-of-words vector: each word adds to a few hashed dimensions, so
     * texts that share words have a high cosine similarity.
     *
     * @return list<float>
     */
    protected function fakeVector(string $text, int $dimensions): array
    {
        $vector = array_fill(0, $dimensions, 0.0);

        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $vector[crc32($word) % $dimensions] += 1.0;
            $vector[crc32('x'.$word) % $dimensions] += 0.5;
        }

        $norm = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $vector))) ?: 1.0;

        return array_map(fn (float $v): float => $v / $norm, $vector);
    }
}
