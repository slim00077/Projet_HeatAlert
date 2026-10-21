<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Basic word-overlap matching, used as the explicitly labelled fallback when the AI is not available
 * and to pre-select the most promising candidates before calling the AI.
 */
class KeywordMatcher
{
    private const STOP_WORDS = [
        'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'has', 'are', 'was', 'were', 'not', 'but', 'our',
        'all', 'its', 'been', 'since', 'there', 'they', 'their', 'very', 'into', 'some', 'when', 'still', 'again', 'any',
        'too', 'also', 'just', 'then', 'keeps', 'even', 'never', 'always', 'les', 'des', 'une', 'est', 'dans', 'pour', 'pas', 'sur', 'avec', 'qui', 'que', 'nous', 'par', 'plus', 'aux',
    ];

    /**
     * Small fixed vocabulary grouping everyday words about the same kind of problem, so basic matching
     * can link differently worded reports ("AC stopped" / "cooling is broken"). This is a dictionary
     * lookup, not semantic AI analysis.
     *
     * @var array<string, list<string>>
     */
    private const CONCEPTS = [
        'cooling / air conditioning' => ['ac', 'aircon', 'cooling', 'cooler', 'cold', 'conditioning', 'climatisation', 'clim', 'hot', 'boiling', 'temperature'],
        'power / electricity' => ['power', 'electricity', 'electrical', 'electric', 'outage', 'blackout', 'breaker', 'fuse', 'cut', 'cuts', 'voltage', 'panne'],
        'elevator' => ['elevator', 'elevators', 'lift', 'ascenseur'],
        'water leak / plumbing' => ['leak', 'leaks', 'leaking', 'drip', 'dripping', 'pipe', 'pipes', 'plumbing', 'flood', 'flooding', 'damp'],
        'ventilation' => ['fan', 'fans', 'ventilation', 'window', 'windows', 'airflow', 'vent'],
        'building structure' => ['crack', 'cracks', 'cracked', 'ceiling', 'wall', 'walls', 'structural', 'concrete'],
    ];

    /**
     * Names of the vocabulary concepts a text touches.
     *
     * @return list<string>
     */
    public function concepts(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_keys(array_filter(
            self::CONCEPTS,
            fn (array $vocabulary) => array_intersect($words, $vocabulary) !== [],
        )));
    }

    /**
     * @return list<string>
     */
    public function sharedConcepts(string $first, string $second): array
    {
        return array_values(array_intersect($this->concepts($first), $this->concepts($second)));
    }

    /**
     * @return list<string>
     */
    public function terms(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::STOP_WORDS, true),
        )));
    }

    /**
     * Word overlap (Jaccard) of the two texts, plus a fixed bonus of 0.25 when they share a vocabulary concept; from 0 to 1.
     */
    public function similarity(string $first, string $second): float
    {
        $a = $this->terms($first);
        $b = $this->terms($second);

        $overlap = ($a === [] || $b === []) ? 0.0 : count(array_intersect($a, $b)) / count(array_unique(array_merge($a, $b)));

        return min(1.0, $overlap + ($this->sharedConcepts($first, $second) !== [] ? 0.25 : 0.0));
    }

    /**
     * @return list<string>
     */
    public function sharedTerms(string $first, string $second, int $limit = 4): array
    {
        return array_slice(array_values(array_intersect($this->terms($first), $this->terms($second))), 0, $limit);
    }
}
