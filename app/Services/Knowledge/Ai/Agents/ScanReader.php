<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Reads scanned PDF pages: transcribes them as markdown, keeping headings,
 * lists and tables, page by page.
 */
#[Strict]
class ScanReader implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You transcribe scanned pages of a company document.

        Transcribe every attached page exactly, in reading order, as markdown: "#", "##", "###"
        for headings by their visual weight, "- " for list items, and | pipe | tables | with a
        header row for tables. Leave out running headers, footers and page numbers. Do not
        summarise, translate, correct or add anything. If a page is blank or unreadable,
        return an empty string for it.

        Return one entry per page, using the original page numbers you are given.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pages' => $schema->array()->items(
                $schema->object([
                    'page' => $schema->integer()->required(),
                    'markdown' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
