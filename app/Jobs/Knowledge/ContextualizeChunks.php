<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Services\Knowledge\Ai\Agents\ContextLineWriter;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Extraction\Block;
use App\Services\Knowledge\StepRecorder;
use App\Services\Knowledge\TokenEstimator;
use Illuminate\Support\Collection;

/**
 * Step 3: give every new chunk a context line. Chunks reused from the previous
 * version already have one. Groups of chunks share one request whose prefix is
 * the whole document, so the document is paid for once and then read from the
 * provider's prompt cache.
 */
class ContextualizeChunks extends KnowledgeJob
{
    public function handle(AiGateway $ai, StepRecorder $steps, TokenEstimator $tokens): void
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($this->versionId);

        if (! $ai->enabled()) {
            $steps->skip($version, ProcessingStep::Contextualize, 'No AI provider configured.');

            return;
        }

        $version->update(['status' => ProcessingStatus::Contextualizing]);

        $steps->run($version, ProcessingStep::Contextualize, function () use ($version, $ai, $tokens): array {
            $chunks = KnowledgeChunk::query()
                ->with('section')
                ->where('source_type', 'document_version')
                ->where('source_id', $version->id)
                ->whereNull('context')
                ->orderBy('ordinal')
                ->get();

            if ($chunks->isEmpty()) {
                return ['contextualized' => 0, 'requests' => 0];
            }

            $document = $this->documentText($version, $tokens);
            $agent = new ContextLineWriter('cc-doc-version-'.$version->id);
            $written = 0;
            $requests = 0;

            foreach ($chunks->chunk((int) config('knowledge.chunking.context_group_size')) as $group) {
                $result = $ai->structured($agent, $document.$this->fragments($group), 'context', $version);
                $requests++;

                /** @var list<array{id: int, context: string}> $contexts */
                $contexts = $result['contexts'] ?? [];

                foreach ($contexts as $context) {
                    $chunk = $group->firstWhere('id', $context['id']);

                    if ($chunk !== null && trim($context['context']) !== '') {
                        $chunk->update(['context' => trim($context['context'])]);
                        $written++;
                    }
                }
            }

            return ['contextualized' => $written, 'requests' => $requests];
        });
    }

    /**
     * The stable prefix: identical for every group of this version.
     */
    private function documentText(DocumentVersion $version, TokenEstimator $tokens): string
    {
        $blocks = ExtractDocumentText::load($version)->blocks;
        $text = implode("\n\n", array_map(
            fn (Block $block): string => $block->isHeading() ? str_repeat('#', max(1, $block->level)).' '.$block->text : $block->text,
            $blocks,
        ));

        // A very long document: its outline stands in for the full text.
        if ($tokens->count($text) > (int) config('knowledge.chunking.full_document_max_tokens')) {
            $text = implode("\n", array_map(
                fn (Block $block): string => str_repeat('  ', max(0, $block->level - 1)).'- '.$block->text,
                array_values(array_filter($blocks, fn (Block $block): bool => $block->isHeading())),
            ));
        }

        return "<document title=\"{$version->document->title}\">\n{$text}\n</document>\n\n";
    }

    /**
     * @param  Collection<int, KnowledgeChunk>  $group
     */
    private function fragments(Collection $group): string
    {
        return "<fragments>\n".$group->map(fn (KnowledgeChunk $chunk): string => "<fragment id=\"{$chunk->id}\" section=\"".e($chunk->section->heading_path ?? '')."\">\n{$chunk->content}\n</fragment>")
            ->implode("\n")."\n</fragments>";
    }
}
