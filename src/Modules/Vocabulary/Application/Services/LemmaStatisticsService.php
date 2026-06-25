<?php

/**
 * Lemma Statistics Service
 *
 * Handles lemma statistics and cleanup operations.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\Services;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Service for lemma statistics and cleanup operations.
 *
 * @since 3.0.0
 */
class LemmaStatisticsService
{
    /**
     * Get lemma statistics for a language.
     *
     * @param int $languageId Language ID
     *
     * @return array{total_terms: int, with_lemma: int, without_lemma: int, unique_lemmas: int}
     */
    public function getLemmaStatistics(int $languageId): array
    {
        $bindings = [$languageId];

        $total = (int) Connection::preparedFetchValue(
            "SELECT COUNT(*) as cnt FROM words WHERE language_id = ? AND word_count = 1"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'cnt'
        );

        $bindings = [$languageId];
        $withLemma = (int) Connection::preparedFetchValue(
            "SELECT COUNT(*) as cnt FROM words
             WHERE language_id = ? AND word_count = 1 AND lemma IS NOT NULL AND lemma != ''"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'cnt'
        );

        $bindings = [$languageId];
        $uniqueLemmas = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT lemma_lc) as cnt FROM words
             WHERE language_id = ? AND word_count = 1 AND lemma_lc IS NOT NULL AND lemma_lc != ''"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'cnt'
        );

        return [
            'total_terms' => $total,
            'with_lemma' => $withLemma,
            'without_lemma' => $total - $withLemma,
            'unique_lemmas' => $uniqueLemmas,
        ];
    }

    /**
     * Clear all lemmas for a language.
     *
     * @param int $languageId Language ID
     *
     * @return int Number of terms affected
     */
    public function clearLemmas(int $languageId): int
    {
        $bindings = [$languageId];

        return Connection::preparedExecute(
            "UPDATE words SET lemma = NULL, lemma_lc = NULL WHERE language_id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings
        );
    }

    /**
     * Get statistics about unmatched text items that could benefit from lemma linking.
     *
     * @param int $languageId Language ID
     *
     * @return array{unmatched_count: int, unique_words: int, matchable_by_lemma: int}
     */
    public function getUnmatchedStatistics(int $languageId): array
    {
        // word_occurrences has no user_id column — inherit user scope by joining
        // through `texts` (via text_id) and filtering on user_id. Without this,
        // aggregations across word_occurrences silently combine every user's
        // data sharing the language.
        $bindings = [$languageId];

        // Count unmatched items
        $unmatchedCount = (int) Connection::preparedFetchValue(
            "SELECT COUNT(*) as cnt FROM word_occurrences
             JOIN texts ON word_occurrences.text_id = texts.id
             WHERE word_occurrences.language_id = ? AND word_occurrences.word_id IS NULL
               AND word_occurrences.word_count = 1"
            . UserScopedQuery::forTablePrepared('texts', $bindings, 'texts'),
            $bindings,
            'cnt'
        );

        // Count unique unmatched words
        $bindings = [$languageId];
        $uniqueWords = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT LOWER(word_occurrences.text)) as cnt FROM word_occurrences
             JOIN texts ON word_occurrences.text_id = texts.id
             WHERE word_occurrences.language_id = ? AND word_occurrences.word_id IS NULL
               AND word_occurrences.word_count = 1"
            . UserScopedQuery::forTablePrepared('texts', $bindings, 'texts'),
            $bindings,
            'cnt'
        );

        // Count how many unique unmatched words have a potential lemma match
        $bindings = [$languageId, $languageId];
        $matchableByLemma = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT LOWER(ti.text)) as cnt
             FROM word_occurrences ti
             JOIN texts ON ti.text_id = texts.id
             JOIN words w ON w.language_id = ? AND LOWER(ti.text) = w.lemma_lc
             WHERE ti.language_id = ?
               AND ti.word_id IS NULL
               AND ti.word_count = 1
               AND w.word_count = 1"
            . UserScopedQuery::forTablePrepared('texts', $bindings, 'texts')
            . UserScopedQuery::forTablePrepared('words', $bindings, 'w'),
            $bindings,
            'cnt'
        );

        return [
            'unmatched_count' => $unmatchedCount,
            'unique_words' => $uniqueWords,
            'matchable_by_lemma' => $matchableByLemma,
        ];
    }

    /**
     * Get aggregate lemma statistics for a language.
     *
     * @param int $languageId Language ID
     *
     * @return array{
     *     total_lemmas: int, single_form: int, multi_form: int,
     *     avg_forms_per_lemma: float, status_distribution: array
     * }
     */
    public function getLemmaAggregateStats(int $languageId): array
    {
        $bindings = [$languageId];
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);

        // Get family size distribution
        $familyStats = Connection::preparedFetchAll(
            "SELECT family_size, COUNT(*) as lemma_count FROM (
                SELECT COUNT(*) as family_size
                FROM words
                WHERE language_id = ? AND lemma_lc IS NOT NULL AND lemma_lc != ''{$userScope}
                GROUP BY lemma_lc
             ) AS family_sizes
             GROUP BY family_size
             ORDER BY family_size",
            $bindings
        );

        $singleForm = 0;
        $multiForm = 0;
        $totalLemmas = 0;
        $totalForms = 0;

        foreach ($familyStats as $stat) {
            $size = (int) $stat['family_size'];
            $count = (int) $stat['lemma_count'];

            $totalLemmas += $count;
            $totalForms += $size * $count;

            if ($size === 1) {
                $singleForm = $count;
            } else {
                $multiForm += $count;
            }
        }

        // Get status distribution by lemma (average status per family)
        $bindings = [$languageId];
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
        $statusDistribution = Connection::preparedFetchAll(
            "SELECT
                ROUND(AVG(CASE WHEN status <= 5 THEN status ELSE NULL END)) as avg_status,
                COUNT(DISTINCT lemma_lc) as lemma_count
             FROM words
             WHERE language_id = ? AND lemma_lc IS NOT NULL AND lemma_lc != ''{$userScope}
             GROUP BY lemma_lc
             HAVING avg_status IS NOT NULL",
            $bindings
        );

        $statusCounts = array_fill(1, 5, 0);
        foreach ($statusDistribution as $row) {
            $avgStatus = (int) round((float) $row['avg_status']);
            if ($avgStatus >= 1 && $avgStatus <= 5) {
                $statusCounts[$avgStatus] += (int) $row['lemma_count'];
            }
        }

        return [
            'total_lemmas' => $totalLemmas,
            'single_form' => $singleForm,
            'multi_form' => $multiForm,
            'avg_forms_per_lemma' => $totalLemmas > 0 ? round($totalForms / $totalLemmas, 2) : 0,
            'status_distribution' => $statusCounts,
        ];
    }
}
