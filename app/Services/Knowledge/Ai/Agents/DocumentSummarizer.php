<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Summarises a document from its section summaries: what it is, who it is for
 * and what it covers.
 */
#[Strict]
class DocumentSummarizer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You get the outline of a company document with a summary per section. Write a summary of
        the whole document in four to six sentences, in the language of the document: what kind
        of document it is, for whom, from when it applies if stated, and which subjects it covers.
        Do not invent anything that is not in the section summaries.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['summary' => $schema->string()->required()];
    }
}
