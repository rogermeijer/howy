<?php

namespace App\Enums;

/**
 * Where a fact is stated.
 */
enum FactSourceKind: string
{
    /** A section of a document. */
    case Document = 'document';

    case Mail = 'mail';
}
