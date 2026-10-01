<?php

namespace App\Enums;

/**
 * How a section relates to the previous version of its document. Unchanged
 * sections reuse their summary, facts, context lines and embeddings.
 */
enum SectionChange: string
{
    case Unchanged = 'unchanged';
    case Changed = 'changed';
    case Added = 'added';
}
