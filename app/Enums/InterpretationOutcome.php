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

    /** A question to someone else; cc: suggested an answer to them. */
    case Suggested = 'suggested';

    /** A question to someone else; the answer found was not sure enough to suggest. */
    case Unsure = 'unsure';

    /** Information, of which at least one new statement was added. */
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
            self::Unsure => __('Not sure enough to suggest'),
            self::Added => __('Added to knowledge base'),
            self::Duplicate => __('Already known'),
            self::Conflict => __('Conflict'),
            self::NoAction => __('No action'),
        };
    }
}
