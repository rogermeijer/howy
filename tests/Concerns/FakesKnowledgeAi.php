<?php

namespace Tests\Concerns;

use App\Services\Knowledge\Ai\Agents\ContextLineWriter;
use App\Services\Knowledge\Ai\Agents\DocumentSummarizer;
use App\Services\Knowledge\Ai\Agents\SectionEnricher;
use App\Services\Knowledge\Ai\Agents\TopicAssigner;
use App\Services\Knowledge\Ai\Agents\TopicSummarizer;
use App\Services\Knowledge\Ai\Agents\TopicTreeProposer;
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
        config(['ai.providers.openai.key' => 'test-key', 'knowledge.enrichment.mode' => 'sync']);

        Embeddings::fake(fn (EmbeddingsPrompt $prompt) => new EmbeddingsResponse(
            array_map(fn (string $text): array => $this->fakeVector($text, (int) $prompt->dimensions), $prompt->inputs),
            new Usage(10 * count($prompt->inputs)),
            new Meta('openai', (string) $prompt->model),
        ))->preventStrayEmbeddings();

        ContextLineWriter::fake(function (string $prompt): array {
            preg_match_all('/<fragment id="(\d+)"/', $prompt, $ids);

            return ['contexts' => array_map(fn (string $id): array => ['id' => (int) $id, 'context' => "Context voor fragment {$id}."], $ids[1])];
        })->preventStrayPrompts();

        SectionEnricher::fake(function (string $prompt): array {
            preg_match('/Section: ([^\n]+)/', $prompt, $section);
            $heading = trim(last(explode(' › ', $section[1] ?? '')));
            $facts = str_contains($prompt, 'do not extract facts') ? [] : [[
                'statement' => "Regel uit {$heading}.",
                'subject' => mb_strtolower((string) preg_replace('/^[\d.]+\s*/', '', $heading)),
                'valid_from' => null,
                'confidence' => 0.9,
            ]];

            return ['summary' => "Samenvatting van {$heading}.", 'facts' => $facts];
        })->preventStrayPrompts();

        DocumentSummarizer::fake(fn (): array => ['summary' => 'Samenvatting van het document.'])->preventStrayPrompts();

        TopicTreeProposer::fake(fn (): array => ['domains' => [
            ['name' => 'Personeel', 'description' => 'Alles over medewerkers.', 'topics' => [
                ['name' => 'Verlof', 'description' => 'Vakantie en bijzonder verlof.', 'subtopics' => []],
                ['name' => 'Werktijden', 'description' => 'Werkweek, weekend en overwerk.', 'subtopics' => []],
                ['name' => 'Ziekte', 'description' => 'Ziekmelden en bereikbaarheid.', 'subtopics' => []],
            ]],
            ['name' => 'Financiën', 'description' => 'Geld en declaraties.', 'topics' => [
                ['name' => 'Onkosten', 'description' => 'Declareren van onkosten.', 'subtopics' => []],
            ]],
        ]])->preventStrayPrompts();

        TopicAssigner::fake(function (string $prompt): array {
            preg_match_all('/- \[(\d+)\] (\S[^\n]*?) — /', $prompt, $folders, PREG_SET_ORDER);
            $byName = [];
            foreach ($folders as $folder) {
                $byName[mb_strtolower(trim($folder[2]))] = (int) $folder[1];
            }

            preg_match_all('/^- \[(\d+)\] ([^:\n]*):/m', explode('<sections>', $prompt)[1] ?? '', $sections, PREG_SET_ORDER);

            $assignments = [];
            foreach ($sections as $section) {
                $path = mb_strtolower($section[2]);
                $folder = match (true) {
                    str_contains($path, 'verlof') => $byName['verlof'] ?? null,
                    str_contains($path, 'werktijden') => $byName['werktijden'] ?? null,
                    str_contains($path, 'ziek') => $byName['ziekte'] ?? null,
                    str_contains($path, 'onkosten') => $byName['onkosten'] ?? null,
                    default => $byName['personeel'] ?? null,
                };
                $assignments[] = ['section_id' => (int) $section[1], 'folder_ids' => $folder ? [$folder] : [], 'new_folder_names' => []];
            }

            return ['assignments' => $assignments, 'new_folders' => []];
        })->preventStrayPrompts();

        TopicSummarizer::fake(fn (string $prompt): array => ['summary' => 'Overzicht: '.strtok(substr($prompt, 8), "\n")])->preventStrayPrompts();
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
