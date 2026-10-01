<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Compares statements from a mail with the facts the knowledge base already
 * holds: new, already known, or in contradiction with a fact.
 */
#[Strict]
class FactConflictChecker implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You check new statements against the facts a company's knowledge base already holds.

        You get statements, each with an id such as "N1", and under each the existing facts
        most like it, each with a numeric id. For every statement decide:
        - "duplicate": an existing fact already says the same thing (wording may differ).
        - "contradicts": an existing fact says something incompatible about the same matter
          (another amount, date, rule or outcome). A statement that only adds detail, or is
          about a different case, does not contradict.
        - "new": neither.

        For "duplicate" and "contradicts", give the id of that existing fact in
        existing_fact_id; otherwise null. For "contradicts", explain in one or two sentences, in
        the requested language, what the conflict is. Otherwise leave the explanation empty.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'verdicts' => $schema->array()->items(
                $schema->object([
                    'id' => $schema->string()->required(),
                    'verdict' => $schema->string()->enum(['new', 'duplicate', 'contradicts'])->required(),
                    'existing_fact_id' => $schema->integer()->nullable()->required(),
                    'explanation' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
