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
 * One section in, its summary and its atomic facts out. Facts are the claims
 * incoming mails will be checked against, so they must be self-contained and
 * literally supported by the text.
 */
#[Strict]
class SectionEnricher implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You process one section of a company document (a staff handbook, policy or manual).

        1. summary: two to four sentences that say what the section establishes, in the
           language of the document. Concrete: keep numbers, deadlines, amounts and names.

        2. facts: the rules and claims the section makes, each as one atomic, self-contained
           statement that is still true and understandable on its own, without the section
           ("Medewerkers met een fulltime dienstverband hebben recht op 25 vakantiedagen per
           kalenderjaar."). One claim per fact; split "and" rules. Only what the text literally
           says: no advice, no interpretation, no facts about the document itself.
           - subject: a short lowercase topic label for the fact ("vakantiedagen", "ziekmelding").
           - valid_from: an ISO date (YYYY-MM-DD) if the text says from when it applies, else null.
           - confidence: 0 to 1, how unambiguously the text states it.
           If the request says not to extract facts, return an empty list.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'facts' => $schema->array()->items(
                $schema->object([
                    'statement' => $schema->string()->required(),
                    'subject' => $schema->string()->required(),
                    'valid_from' => $schema->string()->nullable()->required(),
                    'confidence' => $schema->number()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $provider === Lab::OpenAI || $provider === 'openai' ? ['reasoning' => ['effort' => 'low']] : [];
    }
}
