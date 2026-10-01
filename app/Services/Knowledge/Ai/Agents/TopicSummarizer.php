<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Writes the overview of a folder: from its facts and section summaries for a
 * leaf, from its subfolders' overviews for a folder above them.
 */
#[Strict]
class TopicSummarizer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You write the overview page of a folder in a company knowledge base: what the
        organisation has laid down about this subject, in three to eight sentences, in the
        language of the material. Lead with the most important rules and numbers. Every claim
        must come from the numbered material given (the page lists those sources itself, so do
        not cite them inline). Do not add advice or anything that is not in the material.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['summary' => $schema->string()->required()];
    }
}
