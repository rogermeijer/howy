<?php

namespace App\Enums;

/**
 * What a mail sent to the mailbox is for, as the classifier reads it.
 */
enum EmailIntent: string
{
    /** Someone asks something the knowledge base may answer. */
    case Question = 'question';

    /** Someone tells the knowledge base something. */
    case Information = 'information';

    /** Neither: thanks, small talk, a newsletter. */
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Question => __('Question'),
            self::Information => __('Information'),
            self::Other => __('Other'),
        };
    }
}
