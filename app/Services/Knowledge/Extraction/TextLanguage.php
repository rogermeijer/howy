<?php

namespace App\Services\Knowledge\Extraction;

use App\Enums\Locale;

/**
 * Which of the languages we ship a text is written in, told by its most
 * common words. Enough to pick the search configuration of a file nobody
 * described, such as a mail attachment; a text too short, mixed, or in
 * another language is not guessed.
 */
class TextLanguage
{
    /** Words that are common in one language and rare in the others. */
    private const array WORDS = [
        'en' => ['the', 'and', 'to', 'are', 'for', 'with', 'that', 'this', 'be', 'on', 'by', 'an', 'as', 'from', 'or', 'it', 'at', 'you', 'your', 'our', 'will', 'can', 'have', 'has', 'not', 'if', 'which', 'when', 'all', 'they'],
        'nl' => ['de', 'het', 'een', 'en', 'van', 'zijn', 'voor', 'met', 'dat', 'die', 'op', 'te', 'aan', 'als', 'bij', 'wordt', 'worden', 'je', 'jouw', 'wij', 'ons', 'onze', 'niet', 'kan', 'heeft', 'naar', 'ook', 'deze', 'om', 'tot'],
    ];

    /** At least this many telling words before a guess is made. */
    private const int MIN_HITS = 20;

    /** Read this far; the language is clear long before the end. */
    private const int MAX_WORDS = 5000;

    public static function guess(string $text): ?Locale
    {
        preg_match_all('/\p{L}+/u', mb_strtolower(mb_substr($text, 0, self::MAX_WORDS * 10)), $matches);
        $counts = array_count_values(array_slice($matches[0], 0, self::MAX_WORDS));

        // A language without a word list is never guessed.
        $hits = array_map(fn (array $words): int => array_sum(array_map(fn (string $word): int => $counts[$word] ?? 0, $words)), self::WORDS);

        arsort($hits);
        $ranked = array_values($hits);

        // Clearly one language: not too little to tell, not a mix.
        if ($ranked[0] < self::MIN_HITS || $ranked[0] < $ranked[1] * 2) {
            return null;
        }

        return Locale::tryFrom((string) array_key_first($hits));
    }
}
