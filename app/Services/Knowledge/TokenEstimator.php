<?php

namespace App\Services\Knowledge;

/**
 * A fast token estimate for sizing chunks and budgets. Exact counts come back
 * from the provider and are what usage is recorded with; for chunk boundaries
 * an estimate within ~10% is enough and needs no tokenizer files at runtime.
 *
 * Dutch and English average roughly 3.8 characters per token on current
 * OpenAI tokenizers; long compounds push Dutch slightly higher, which the
 * word-based floor catches.
 */
class TokenEstimator
{
    public function count(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        $byCharacters = mb_strlen($text) / 3.8;
        $byWords = count(preg_split('/\s+/u', $text) ?: []) * 1.3;

        return (int) ceil(max($byCharacters, $byWords));
    }
}
