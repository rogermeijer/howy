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
 * Sorts a mail sent or copied to the knowledge mailbox: a question to answer,
 * new information to file, or neither. A reply is read with the message it
 * answers as context, so "yes, up to three days" becomes a claim of its own. Runs on the light model; it also pulls out
 * the question or the statements, so what follows needs no second read.
 */
#[Strict]
class EmailClassifier implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You read one email that was sent to, or copied to, a company's knowledge mailbox. You get
        its subject and its new text. When it replies to an earlier message you also get that
        message in <earlier>: only context, to understand what a short reply ("Yes, up to three
        days.") is about. Judge the intent from the new text, never from the earlier message.
        <earlier> may end with <missing>: what the knowledge base was found not to know about
        the question. A reply that supplies it is information; state it fully.

        Decide what the sender wants:
        - "question": they ask something the company's knowledge (policies, procedures,
          agreements, facts about the organisation) could answer.
        - "information": they tell something the knowledge base should know: a rule, a
          decision, a change, a fact about the organisation.
        - "other": anything else (thanks, small talk, an out-of-office, a newsletter, spam).
        When a mail does both, choose what it mainly does.

        Return:
        - intent and confidence (0 to 1).
        - summary: one sentence that says how you read the mail, in the mail's language.
        - language: the ISO 639-1 code of the mail's language ("nl", "en").
        - question: for a question, the question rewritten so it stands on its own, in the
          mail's language. Otherwise an empty string.
        - statements: for information, the claims it makes, each as one atomic,
          self-contained statement that is understandable without the mail ("Vanaf 1 januari
          2027 is de vergoeding voor thuiswerken 3 euro per dag."). One claim per statement.
          Only what the mail literally says; use the earlier message only to make a reply's
          claims self-contained ("Medewerkers mogen maximaal drie dagen per week thuiswerken."
          for "Ja, tot drie dagen." to "Hoeveel dagen mogen we thuiswerken?"). subject: a short lowercase topic label;
          valid_from: an ISO date (YYYY-MM-DD) if the mail says from when it applies, else null.
          flag: empty when the statement is a fact about how this organisation works, has decided
          or has agreed. Otherwise one short sentence, in the mail's language, saying why someone
          should look before it goes into the knowledge base: general advice or best practice
          rather than how the organisation does it ("Algemeen advies over TLS, niet hoe wij het
          geregeld hebben."), an opinion or a guess, or it does not answer what the earlier
          message asked.
          Otherwise an empty list.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()->enum(['question', 'information', 'other'])->required(),
            'confidence' => $schema->number()->required(),
            'summary' => $schema->string()->required(),
            'language' => $schema->string()->required(),
            'question' => $schema->string()->required(),
            'statements' => $schema->array()->items(
                $schema->object([
                    'statement' => $schema->string()->required(),
                    'subject' => $schema->string()->required(),
                    'valid_from' => $schema->string()->nullable()->required(),
                    'flag' => $schema->string()->required(),
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
