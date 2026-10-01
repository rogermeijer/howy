<?php

namespace App\Enums;

/**
 * Who made a topic or a topic link. AI never overwrites or removes anything a
 * person made.
 */
enum KnowledgeOrigin: string
{
    case Ai = 'ai';
    case Manual = 'manual';
}
