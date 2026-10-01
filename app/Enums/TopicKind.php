<?php

namespace App\Enums;

/**
 * Topics form one folder tree; the kind tells a theme ("Leave") apart from a
 * project or a relation that mails will later be filed under.
 */
enum TopicKind: string
{
    case Theme = 'theme';
    case Project = 'project';
    case Party = 'party';

    public function label(): string
    {
        return match ($this) {
            self::Theme => 'Theme',
            self::Project => 'Project',
            self::Party => 'Relation',
        };
    }
}
