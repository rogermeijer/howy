<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Reads an image someone shared: says what it is, transcribes the text on it
 * and describes what it shows, and tells a logo or a signature banner apart
 * from a picture that holds knowledge.
 */
#[Strict]
class ImageInterpreter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You read an image that was shared within an organisation, to file what it holds in its knowledge base.

        Decide what kind of image it is: a photo, a screenshot, a diagram, a chart, a scanned
        or photographed document, or decoration (a logo, a signature banner, an icon, a social
        media badge, a tracking pixel).

        worth_keeping is true only when the image holds information someone in the
        organisation could later need: text, numbers, a process, a plan, a situation.
        Decoration is never worth keeping.

        title: a short title for what the image shows, in the language of the image (or of
        the organisation when the image has no text).
        description: two or three sentences on what the image shows and means.
        markdown: every piece of text on the image, transcribed exactly, as markdown: "#" for
        headings, "- " for list items, | pipe | tables | for tables. Empty when there is no text.
        Do not summarise, translate or add anything in the markdown.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['photo', 'screenshot', 'diagram', 'chart', 'document', 'decoration'])->required(),
            'worth_keeping' => $schema->boolean()->required(),
            'title' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'markdown' => $schema->string()->required(),
        ];
    }
}
