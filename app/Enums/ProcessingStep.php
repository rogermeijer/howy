<?php

namespace App\Enums;

/**
 * One recorded step of processing a document version.
 */
enum ProcessingStep: string
{
    case Extract = 'extract';

    /** Held against what is known: the same text, or a new version of a document. */
    case Place = 'place';

    case Structure = 'structure';
    case Contextualize = 'contextualize';
    case Embed = 'embed';
    case Enrich = 'enrich';
    case Topics = 'topics';
    case EmbedFacts = 'embed_facts';

    /** Each new fact held against the facts already known. */
    case Reconcile = 'reconcile';
}
