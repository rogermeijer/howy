<?php

namespace App\Services\Knowledge;

use App\Enums\Locale;
use App\Facades\Tenancy;

/**
 * The one language the knowledge base is built in, set per account. What Howy
 * writes itself (facts, summaries, folder names, statements from mail) is in
 * it, whatever language the source was in, so the same claim from a Dutch
 * and an English document is recognised as one. A document's own text stays
 * as it is.
 */
class KnowledgeLanguage
{
    public static function locale(): Locale
    {
        return Tenancy::account()->knowledge_locale;
    }

    /**
     * The line that tells a model which language to write in.
     */
    public static function instruction(): string
    {
        $locale = self::locale();

        return "Write in: {$locale->label()} ({$locale->value})";
    }

    /**
     * How facts written in it are searched.
     */
    public static function searchConfiguration(): string
    {
        return self::locale()->searchConfiguration();
    }
}
