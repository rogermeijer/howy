<?php

namespace App\Services\Knowledge\Evaluation;

use App\Services\Knowledge\Search\FactHit;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\Passage;
use App\Services\Knowledge\Search\SearchQuery;

/**
 * Measures retrieval against a set of questions with known sources.
 *
 * Per question: is an expected source among the first k passages (hit@k), at
 * which rank (reciprocal rank), how many expected sources came back (recall),
 * whether an expected fact is in the result, and the tokens and time spent.
 * Questions marked "none" expect nothing: returning passages there is noise.
 *
 * @phpstan-type Question array{id: string, category: string, question: string, expected: list<array{document: string, section: string}>, expected_fact?: string|null}
 * @phpstan-type Outcome array{id: string, category: string, question: string, hit: bool, reciprocal_rank: float, recall: float, fact_found: bool|null, passages: int, tokens: int, milliseconds: int, top: list<string>}
 */
class KnowledgeEvaluator
{
    public function __construct(private readonly KnowledgeSearch $search) {}

    /**
     * @param  list<Question>  $questions
     * @return array{summary: array<string, float|int>, by_category: array<string, array<string, float|int>>, questions: list<Outcome>}
     */
    public function run(array $questions, int $k = 5, ?int $maxTokens = null): array
    {
        $outcomes = [];

        foreach ($questions as $question) {
            $started = hrtime(true);
            $result = $this->search->search(new SearchQuery($question['question'], maxTokens: $maxTokens));
            $milliseconds = (int) round((hrtime(true) - $started) / 1_000_000);

            $outcomes[] = $this->score($question, $result->passages, $result->facts, $result->tokenCount, $milliseconds, $k);
        }

        $categories = [];

        foreach ($outcomes as $outcome) {
            $categories[$outcome['category']][] = $outcome;
        }

        return [
            'summary' => $this->aggregate($outcomes),
            'by_category' => array_map(fn (array $group): array => $this->aggregate($group), $categories),
            'questions' => $outcomes,
        ];
    }

    /**
     * @param  Question  $question
     * @param  list<Passage>  $passages
     * @param  list<FactHit>  $facts
     * @return Outcome
     */
    public function score(array $question, array $passages, array $facts, int $tokens, int $milliseconds, int $k): array
    {
        $expected = $question['expected'];
        $none = $expected === [];
        $rank = null;
        $found = [];

        foreach (array_slice($passages, 0, $k) as $index => $passage) {
            foreach ($expected as $key => $source) {
                if ($this->matches($passage, $source)) {
                    $rank ??= $index + 1;
                    $found[$key] = true;
                }
            }
        }

        $fact = $question['expected_fact'] ?? null;
        $haystack = mb_strtolower(implode("\n", [
            ...array_map(fn (FactHit $hit): string => $hit->statement, $facts),
            ...array_map(fn (Passage $passage): string => $passage->text, $passages),
        ]));

        return [
            'id' => $question['id'],
            'category' => $question['category'],
            'question' => $question['question'],
            'hit' => $none ? $passages === [] : $rank !== null,
            'reciprocal_rank' => $none ? ($passages === [] ? 1.0 : 0.0) : ($rank === null ? 0.0 : 1 / $rank),
            'recall' => $none ? 1.0 : count($found) / count($expected),
            'fact_found' => $fact === null ? null : str_contains($haystack, mb_strtolower($fact)),
            'passages' => count($passages),
            'tokens' => $tokens,
            'milliseconds' => $milliseconds,
            'top' => array_map(fn (Passage $passage): string => $passage->citation(), array_slice($passages, 0, 3)),
        ];
    }

    /**
     * @param  array{document: string, section: string}  $source
     */
    private function matches(Passage $passage, array $source): bool
    {
        return str_contains(mb_strtolower($passage->documentTitle), mb_strtolower($source['document']))
            && str_contains(mb_strtolower($passage->headingPath), mb_strtolower($source['section']));
    }

    /**
     * @param  list<Outcome>  $outcomes
     * @return array<string, float|int>
     */
    private function aggregate(array $outcomes): array
    {
        $count = max(1, count($outcomes));
        $times = array_map(fn (array $outcome): int => $outcome['milliseconds'], $outcomes);
        sort($times);
        $facts = array_values(array_filter($outcomes, fn (array $outcome): bool => $outcome['fact_found'] !== null));

        return [
            'questions' => count($outcomes),
            'hit_rate' => round(array_sum(array_map(fn (array $outcome): int => (int) $outcome['hit'], $outcomes)) / $count, 3),
            'mrr' => round(array_sum(array_map(fn (array $outcome): float => $outcome['reciprocal_rank'], $outcomes)) / $count, 3),
            'recall' => round(array_sum(array_map(fn (array $outcome): float => $outcome['recall'], $outcomes)) / $count, 3),
            'fact_rate' => $facts === [] ? 0 : round(count(array_filter($facts, fn (array $outcome): bool => (bool) $outcome['fact_found'])) / count($facts), 3),
            'avg_tokens' => (int) round(array_sum(array_map(fn (array $outcome): int => $outcome['tokens'], $outcomes)) / $count),
            'p50_ms' => $times === [] ? 0 : $times[(int) floor((count($times) - 1) * 0.5)],
            'p95_ms' => $times === [] ? 0 : $times[(int) floor((count($times) - 1) * 0.95)],
        ];
    }
}
