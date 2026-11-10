<?php

namespace App\Enums;

/**
 * What came of interpreting a mail.
 */
enum InterpretationOutcome: string
{
    /** A question, answered from the knowledge base. */
    case Answered = 'answered';

    /** A question the knowledge base has no answer to. */
    case NotFound = 'not_found';

    /**
     * A question to someone else; Howy suggested an answer to them. No longer
     * produced (copied, Howy answers nothing), kept for mail read before.
     */
    case Suggested = 'suggested';

    /** A question with an answer not sure enough to send; it is kept, not sent. */
    case Unsure = 'unsure';

    /** Information with at least one new statement, proposed for the knowledge base. */
    case Added = 'added';

    /** Information the knowledge base already held. */
    case Duplicate = 'duplicate';

    /** Information that contradicts the knowledge base; held for review. */
    case Conflict = 'conflict';

    /** Nothing to answer or add. */
    case NoAction = 'no_action';

    public function label(): string
    {
        return match ($this) {
            self::Answered => __('Answered'),
            self::NotFound => __('No answer found'),
            self::Suggested => __('Answer suggested'),
            self::Unsure => __('Not sure enough to answer'),
            self::Added => __('Proposed for the knowledge base'),
            self::Duplicate => __('Already known'),
            self::Conflict => __('Conflict'),
            self::NoAction => __('No action'),
        };
    }
}
