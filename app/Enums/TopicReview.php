<?php

namespace App\Enums;

/**
 * AI-created folders appear straight away, marked new until an admin approves,
 * renames, merges or removes them.
 */
enum TopicReview: string
{
    case New = 'new';
    case Approved = 'approved';
}
