<?php

namespace App\Enums;

/**
 * What the person Howy answered thought of the answer, from the links under
 * it (05).
 */
enum AnswerRating: string
{
    case Valuable = 'valuable';

    case Partly = 'partly';

    case Outdated = 'outdated';

    public function label(): string
    {
        return match ($this) {
            self::Valuable => __('Valuable'),
            self::Partly => __('Not quite'),
            self::Outdated => __('Outdated'),
        };
    }
}
