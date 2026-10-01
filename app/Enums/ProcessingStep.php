<?php

namespace App\Enums;

/**
 * One recorded step of processing a document version.
 */
enum ProcessingStep: string
{
    case Extract = 'extract';
    case Structure = 'structure';
    case Contextualize = 'contextualize';
    case Embed = 'embed';
    case Enrich = 'enrich';
    case EmbedFacts = 'embed_facts';
}
