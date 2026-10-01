<?php

namespace App\Enums;

/**
 * What kind of document an upload is. Used to filter searches, e.g. "only
 * policies" when a mail is checked against company rules.
 */
enum DocumentType: string
{
    case Handbook = 'handbook';
    case Policy = 'policy';
    case Manual = 'manual';
    case Documentation = 'documentation';
    case Other = 'other';

    /**
     * English source string; translated at the display boundary.
     */
    public function label(): string
    {
        return match ($this) {
            self::Handbook => 'Handbook',
            self::Policy => 'Policy',
            self::Manual => 'Manual',
            self::Documentation => 'Documentation',
            self::Other => 'Other',
        };
    }
}
