<?php

namespace App\Enums;

/**
 * How a file reached the knowledge base.
 */
enum SourceKind: string
{
    case Upload = 'upload';
    case Mail = 'mail';
}
