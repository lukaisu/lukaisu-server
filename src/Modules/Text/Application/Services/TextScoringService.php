<?php

/**
 * Text Scoring Service - Comprehensibility and difficulty scoring.
 *
 * Calculates how readable a text is for a user based on their vocabulary knowledge.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Application\Services;

use Lukaisu\Modules\Text\Domain\TextScore;
use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermStatus;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Service for calculating text difficulty scores.
 *
 * Analyzes texts against the user's known vocabulary to determine
 * comprehensibility - useful for recommending appropriate reading material.
 *
 * @since 3.0.0
 */
class TextScoringService
{
    /**
     * Calculate the difficulty score for a single text.
     *
     * @param int $textId            The text ID to score
     * @param int $unknownWordsLimit Maximum unknown words to return in preview
     *
     * @return TextScore The calculated score
     */
    public function scoreText(int $textId, int $unknownWordsLimit = 20): TextScore
    {
        // word_occurrences is not user-scoped at the column level; it inherits
        // scope through its text_id -> texts FK. We must verify ownership
        // before running the queries, otherwise a non-owner can read another
        // user's unknown-word list and vocabulary stats by guessing TxIDs.
        if (!$this->ownsText($textId)) {
            return new TextScore(
                textId: $textId,
                totalUniqueWords: 0,
                knownWords: 0,
                learningWords: 0,
                unknownWords: 0,
                unknownWordsList: []
            );
        }

        $stats = $this->calculateVocabularyStats($textId);
        $unknownWordsList = [];

        if ($stats['unknown'] > 0) {
            $unknownWordsList = $this->getUnknownWords($textId, $unknownWordsLimit);
        }

        return new TextScore(
            textId: $textId,
            totalUniqueWords: $stats['total'],
            knownWords: $stats['known'],
            learningWords: $stats['learning'],
            unknownWords: $stats['unknown'],
            unknownWordsList: $unknownWordsList
        );
    }

    /**
     * Score multiple texts at once (for listing/recommendations).
     *
     * @param int[] $textIds Array of text IDs to score
     *
     * @return array<int, TextScore> Map of textId => TextScore
     */
    public function scoreTexts(array $textIds): array
    {
        if (empty($textIds)) {
            return [];
        }

        // Silently drop any TxIDs the caller does not own. word_occurrences
        // has no UsID column, so without this filter a caller could mix
        // owned + unowned IDs and get back stats for the unowned ones too.
        $textIds = $this->filterOwnedTextIds($textIds);
        if (empty($textIds)) {
            return [];
        }

        $scores = [];
        $statsMap = $this->calculateVocabularyStatsForTexts($textIds);

        foreach ($textIds as $textId) {
            $stats = $statsMap[$textId] ?? [
                'total' => 0,
                'known' => 0,
                'learning' => 0,
                'unknown' => 0
            ];

            $scores[$textId] = new TextScore(
                textId: $textId,
                totalUniqueWords: $stats['total'],
                knownWords: $stats['known'],
                learningWords: $stats['learning'],
                unknownWords: $stats['unknown'],
                unknownWordsList: [] // Skip word list for bulk scoring
            );
        }

        return $scores;
    }

    /**
     * Check if the current user owns the given text.
     *
     * `texts` is in QueryBuilder::USER_SCOPED_TABLES so the where + count
     * returns 0 for unowned IDs in multi-user mode. In single-user mode
     * the auto-scope is a no-op and existence is the only check.
     *
     * @param int $textId The text ID to check
     *
     * @return bool True if the text exists and belongs to the caller
     */
    private function ownsText(int $textId): bool
    {
        return \Lukaisu\Shared\Infrastructure\Database\QueryBuilder::table('texts')
            ->where('id', '=', $textId)
            ->count() > 0;
    }

    /**
     * Filter a list of text IDs down to those owned by the current user.
     *
     * Mirrors WordListService::filterOwnedWordIds — runs a single
     * user-scoped SELECT and returns the surviving IDs.
     *
     * @param int[] $textIds Array of text IDs
     *
     * @return int[] Owned IDs (subset of $textIds)
     */
    private function filterOwnedTextIds(array $textIds): array
    {
        if (empty($textIds)) {
            return [];
        }
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($textIds, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('texts', $bindings);
        if ($userScope === '') {
            return array_values(array_map('intval', $textIds));
        }
        $rows = Connection::preparedFetchAll(
            'SELECT id FROM texts WHERE id IN ' . $inClause . $userScope,
            $bindings
        );
        $owned = [];
        foreach ($rows as $row) {
            $owned[] = (int) $row['id'];
        }
        return $owned;
    }

    /**
     * Get texts recommended for reading based on comprehensibility.
     *
     * Returns texts ordered by proximity to optimal comprehensibility (95%).
     *
     * @param int   $languageId              The language to filter by
     * @param float $targetComprehensibility Target comprehensibility (default 0.95)
     * @param int   $limit                   Maximum number of texts to return
     *
     * @return TextScore[] Array of TextScore objects, best matches first
     */
    public function getRecommendedTexts(
        int $languageId,
        float $targetComprehensibility = 0.95,
        int $limit = 10
    ): array {
        // Get all text IDs for this language
        $bindings = [$languageId];
        $sql = "SELECT id FROM texts WHERE language_id = ?"
            . UserScopedQuery::forTablePrepared('texts', $bindings, 'texts');

        $rows = Connection::preparedFetchAll($sql, $bindings);
        $textIds = array_map(
            fn(array $row): int => (int) $row['id'],
            $rows
        );

        if (empty($textIds)) {
            return [];
        }

        // Score all texts
        $scores = $this->scoreTexts($textIds);

        // Sort by proximity to target comprehensibility
        usort(
            $scores,
            function (TextScore $a, TextScore $b) use ($targetComprehensibility): int {
                $diffA = abs($a->comprehensibility() - $targetComprehensibility);
                $diffB = abs($b->comprehensibility() - $targetComprehensibility);
                return $diffA <=> $diffB;
            }
        );

        // Return top N
        return array_slice($scores, 0, $limit);
    }

    /**
     * Calculate vocabulary statistics for a single text.
     *
     * @param int $textId The text ID
     *
     * @return array{total: int, known: int, learning: int, unknown: int}
     */
    private function calculateVocabularyStats(int $textId): array
    {
        $stats = [
            'total' => 0,
            'known' => 0,
            'learning' => 0,
            'unknown' => 0
        ];

        // Count total unique words in text
        // word_occurrences inherits user context via text_id -> texts FK
        $totalQuery = "SELECT COUNT(DISTINCT LOWER(text)) AS cnt
            FROM word_occurrences
            WHERE word_count = 1 AND text_id = ?";

        /**
 * @var int|string|null $total
*/
        $total = Connection::preparedFetchValue($totalQuery, [$textId], 'cnt');
        $stats['total'] = $total !== null ? (int) $total : 0;

        // Count unknown words (not in vocabulary)
        $unknownQuery = "SELECT COUNT(DISTINCT LOWER(text)) AS cnt
            FROM word_occurrences
            WHERE word_count = 1 AND word_id IS NULL AND text_id = ?";

        /**
 * @var int|string|null $unknown
*/
        $unknown = Connection::preparedFetchValue($unknownQuery, [$textId], 'cnt');
        $stats['unknown'] = $unknown !== null ? (int) $unknown : 0;

        // Count words by status (known vs learning)
        $bindings = [$textId];
        $statusQuery = "SELECT text_id AS text, COUNT(DISTINCT word_id) AS unique_cnt, status AS status
            FROM word_occurrences, words
            WHERE word_id IS NOT NULL AND text_id = ? AND word_id = id"
            . UserScopedQuery::forTablePrepared('words', $bindings, 'words')
            . " GROUP BY text_id, status";

        $rows = Connection::preparedFetchAll($statusQuery, $bindings);
        foreach ($rows as $row) {
            $status = (int) $row['status'];
            $count = (int) $row['unique_cnt'];

            if ($status === TermStatus::LEARNED || $status === TermStatus::WELL_KNOWN) {
                $stats['known'] += $count;
            } elseif ($status >= TermStatus::NEW && $status <= TermStatus::LEARNING_4) {
                $stats['learning'] += $count;
            }
            // Ignored words (98) are counted but not classified
        }

        return $stats;
    }

    /**
     * Calculate vocabulary statistics for multiple texts.
     *
     * @param int[] $textIds Array of text IDs
     *
     * @return array<int, array{total: int, known: int, learning: int, unknown: int}>
     */
    private function calculateVocabularyStatsForTexts(array $textIds): array
    {
        /**
 * @var array<int, array{total: int, known: int, learning: int, unknown: int}> $results
*/
        $results = [];
        foreach ($textIds as $textId) {
            $results[$textId] = [
                'total' => 0,
                'known' => 0,
                'learning' => 0,
                'unknown' => 0
            ];
        }

        if (empty($textIds)) {
            return $results;
        }

        // Count total unique words per text
        // word_occurrences inherits user context via text_id -> texts FK
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($textIds, $bindings);
        $totalQuery = "SELECT text_id AS text, COUNT(DISTINCT LOWER(text)) AS cnt
            FROM word_occurrences
            WHERE word_count = 1 AND text_id IN {$inClause}
            GROUP BY text_id";

        $rows = Connection::preparedFetchAll($totalQuery, $bindings);
        foreach ($rows as $row) {
            $textId = (int) $row['text'];
            if (isset($results[$textId])) {
                $results[$textId]['total'] = (int) $row['cnt'];
            }
        }

        // Count unknown words per text
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($textIds, $bindings);
        $unknownQuery = "SELECT text_id AS text, COUNT(DISTINCT LOWER(text)) AS cnt
            FROM word_occurrences
            WHERE word_count = 1 AND word_id IS NULL AND text_id IN {$inClause}
            GROUP BY text_id";

        $rows = Connection::preparedFetchAll($unknownQuery, $bindings);
        foreach ($rows as $row) {
            $textId = (int) $row['text'];
            if (isset($results[$textId])) {
                $results[$textId]['unknown'] = (int) $row['cnt'];
            }
        }

        // Count words by status
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($textIds, $bindings);
        $statusQuery = "SELECT text_id AS text, COUNT(DISTINCT word_id) AS unique_cnt, status AS status
            FROM word_occurrences, words
            WHERE word_id IS NOT NULL AND text_id IN {$inClause} AND word_id = id"
            . UserScopedQuery::forTablePrepared('words', $bindings, 'words')
            . " GROUP BY text_id, status";

        $rows = Connection::preparedFetchAll($statusQuery, $bindings);
        foreach ($rows as $row) {
            $textId = (int) $row['text'];
            $status = (int) $row['status'];
            $count = (int) $row['unique_cnt'];

            if (!isset($results[$textId])) {
                continue;
            }

            if ($status === TermStatus::LEARNED || $status === TermStatus::WELL_KNOWN) {
                $results[$textId]['known'] += $count;
            } elseif ($status >= TermStatus::NEW && $status <= TermStatus::LEARNING_4) {
                $results[$textId]['learning'] += $count;
            }
        }

        return $results;
    }

    /**
     * Get the list of unknown words in a text.
     *
     * @param int $textId The text ID
     * @param int $limit  Maximum number of words to return
     *
     * @return string[] Array of unknown word texts
     */
    private function getUnknownWords(int $textId, int $limit): array
    {
        // Get unique unknown words, ordered by frequency in text (most common first)
        // word_occurrences inherits user context via text_id -> texts FK
        $sql = "SELECT LOWER(text) AS word, COUNT(*) AS freq
            FROM word_occurrences
            WHERE word_count = 1 AND word_id IS NULL AND text_id = ?
            GROUP BY LOWER(text)
            ORDER BY freq DESC, word ASC
            LIMIT ?";

        $rows = Connection::preparedFetchAll($sql, [$textId, $limit]);

        return array_map(
            fn(array $row): string => (string) $row['word'],
            $rows
        );
    }
}
