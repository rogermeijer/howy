<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Answers a question from the knowledge base, and only from it: as far as the
 * sources go, and saying plainly what they do not tell, rather than guessing.
 * That gap is what the people in the conversation can fill in.
 */
#[Strict]
class QuestionAnswerer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You answer a colleague's question by email, using only the sources from the company's
        knowledge base that you are given. Each source has an id such as "S1".

        - Answer only with what the sources say. Never add knowledge of your own, never guess.
        - Answer the part the sources do support, even when they cover only part of the
          question. Set answered to false and leave the answer empty only when the sources say
          nothing useful about the question at all.
        - missing: what the question asks that the sources do not tell, as one short sentence
          in the requested language ("Tot wanneer de certificaten geldig zijn, staat niet in de
          kennisbank."). Empty when the answer is complete. Never fill such a gap yourself.
        - Write the answer in the requested language, as the body of a short, friendly email
          reply: plain text, no greeting line, no signature. Keep numbers, dates and amounts
          exactly as the sources state them. Do not repeat what is missing in the answer.
        - sources: the ids of the sources the answer relies on.
        - confidence: 0 to 1, how sure you are that what you answer is right and is what was
          asked. A correct partial answer can still be sure; a gap lowers it only as far as
          the answer without it is less useful. Below 0.5 when the sources only touch on the
          question or you had to combine loose hints.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'answered' => $schema->boolean()->required(),
            'answer' => $schema->string()->required(),
            'sources' => $schema->array()->items($schema->string())->required(),
            'missing' => $schema->string()->required(),
            'confidence' => $schema->number()->required(),
        ];
    }
}
