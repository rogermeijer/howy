<?php

namespace App\Enums;

/**
 * The standing of an atomic claim. Core facts come from core documents and are
 * what incoming mails get checked against; expired facts are kept for history.
 */
enum FactStatus: string
{
    case Core = 'core';
    case Supplementary = 'supplementary';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core',
            self::Supplementary => 'Supplementary',
            self::Expired => 'Expired',
        };
    }
}
