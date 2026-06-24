<?php

/**
 * Word Bulk Service - Batch operations on multiple words
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
use Lukaisu\Shared\Infrastructure\Database\DB;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Service for batch operations on multiple words.
 *
 * Handles:
 * - Bulk deletion
 * - Bulk status updates
 * - Bulk text transformations
 * - Bulk term saving
 *
 * @since 3.0.0
 */
class WordBulkService
{
    /**
     * Delete multiple words.
     *
     * @param int[] $wordIds Array of word IDs to delete
     *
     * @return int Number of deleted words
     */
    public function deleteMultiple(array $wordIds): int
    {
        if (empty($wordIds)) {
            return 0;
        }

        $ids = array_map('intval', $wordIds);

        // Delete multi-word text items first (before word deletion triggers FK SET NULL)
        QueryBuilder::table('word_occurrences')
            ->where('word_count', '>', 1)
            ->whereIn('word_id', $ids)
            ->deletePrepared();

        // Delete words - FK constraints handle:
        // - Single-word word_occurrences.word_id set to NULL (ON DELETE SET NULL)
        // - word_tag_map deleted (ON DELETE CASCADE)
        $count = QueryBuilder::table('words')
            ->whereIn('id', $ids)
            ->deletePrepared();

        return $count;
    }

    /**
     * Update status for multiple words.
     *
     * @param int[] $wordIds  Array of word IDs
     * @param int   $status   New status value (1-5, 98, 99)
     * @param bool  $relative If true, change status by +1 or -1
     *
     * @return int Number of updated words
     */
    public function updateStatusMultiple(array $wordIds, int $status, bool $relative = false): int
    {
        if (empty($wordIds)) {
            return 0;
        }

        /** @var array<int, int> $ids */
        $ids = array_map('intval', $wordIds);
        $scoreUpdate = TermStatusService::makeScoreRandomInsertUpdate('u');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($relative) {
            if ($status > 0) {
                // Increment status
                $sql = "UPDATE words
                        SET status = status + 1, status_changed_at = NOW(), {$scoreUpdate}
                        WHERE status IN (1,2,3,4) AND id IN ({$placeholders})"
                        . UserScopedQuery::forTablePrepared('words', $ids);
                return Connection::preparedExecute($sql, $ids);
            } else {
                // Decrement status
                $sql = "UPDATE words
                        SET status = status - 1, status_changed_at = NOW(), {$scoreUpdate}
                        WHERE status IN (2,3,4,5) AND id IN ({$placeholders})"
                        . UserScopedQuery::forTablePrepared('words', $ids);
                return Connection::preparedExecute($sql, $ids);
            }
        }

        // Absolute status
        /** @var array<int, int> $bindings */
        $bindings = array_merge([$status], $ids);
        $sql = "UPDATE words
                SET status = ?, status_changed_at = NOW(), {$scoreUpdate}
                WHERE id IN ({$placeholders})"
                . UserScopedQuery::forTablePrepared('words', $bindings);

        return Connection::preparedExecute($sql, $bindings);
    }

    /**
     * Update status changed date for multiple words.
     *
     * @param int[] $wordIds Array of word IDs
     *
     * @return int Number of updated words
     */
    public function updateStatusDateMultiple(array $wordIds): int
    {
        if (empty($wordIds)) {
            return 0;
        }

        /** @var array<int, int> $ids */
        $ids = array_map('intval', $wordIds);
        $scoreUpdate = TermStatusService::makeScoreRandomInsertUpdate('u');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "UPDATE words
                SET status_changed_at = NOW(), {$scoreUpdate}
                WHERE id IN ({$placeholders})"
                . UserScopedQuery::forTablePrepared('words', $ids);

        return Connection::preparedExecute($sql, $ids);
    }

    /**
     * Delete sentences for multiple words.
     *
     * @param int[] $wordIds Array of word IDs
     *
     * @return int Number of updated words
     */
    public function deleteSentencesMultiple(array $wordIds): int
    {
        if (empty($wordIds)) {
            return 0;
        }

        /** @var array<int, int> $ids */
        $ids = array_map('intval', $wordIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "UPDATE words SET sentence = NULL WHERE id IN ({$placeholders})"
            . UserScopedQuery::forTablePrepared('words', $ids);

        return Connection::preparedExecute($sql, $ids);
    }

    /**
     * Convert words to lowercase.
     *
     * @param int[] $wordIds Array of word IDs
     *
     * @return int Number of updated words
     */
    public function toLowercaseMultiple(array $wordIds): int
    {
        if (empty($wordIds)) {
            return 0;
        }

        /** @var array<int, int> $ids */
        $ids = array_map('intval', $wordIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "UPDATE words SET text = text_lc WHERE id IN ({$placeholders})"
            . UserScopedQuery::forTablePrepared('words', $ids);

        return Connection::preparedExecute($sql, $ids);
    }

    /**
     * Capitalize words.
     *
     * @param int[] $wordIds Array of word IDs
     *
     * @return int Number of updated words
     */
    public function capitalizeMultiple(array $wordIds): int
    {
        if (empty($wordIds)) {
            return 0;
        }

        /** @var array<int, int> $ids */
        $ids = array_map('intval', $wordIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "UPDATE words
             SET text = CONCAT(UPPER(LEFT(text_lc, 1)), SUBSTRING(text_lc, 2))
             WHERE id IN ({$placeholders})"
             . UserScopedQuery::forTablePrepared('words', $ids);

        return Connection::preparedExecute($sql, $ids);
    }

    /**
     * Save multiple terms in bulk.
     *
     * Used by the bulk translate feature to save multiple words at once.
     *
     * @param array<int, array{lg: int, text: string, status: int, trans?: string}> $terms Array of term data
     *
     * @return int The max word ID before insertion (for finding new words)
     */
    public function bulkSaveTerms(array $terms): int
    {
        $bindings = [];
        /** @var int $max */
        $max = (int) Connection::preparedFetchValue(
            "SELECT COALESCE(MAX(id), 0) AS max_id FROM words WHERE 1=1"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'max_id'
        );

        if (empty($terms)) {
            return $max;
        }

        $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

        DB::beginTransaction();
        try {
            // Insert each term using prepared statements for safety
            foreach ($terms as $row) {
                $trans = (!isset($row['trans']) || $row['trans'] == '') ? '*' : $row['trans'];
                $textlc = mb_strtolower($row['text'], 'UTF-8');

                $bindings = [$row['lg'], $textlc, $row['text'], $row['status'], $trans];
                $sql = "INSERT INTO words (
                        language_id, text_lc, text, status, translation, sentence,
                        romanization, status_changed_at, {$scoreColumns}"
                        . UserScopedQuery::insertColumn('words')
                    . ") VALUES (?, ?, ?, ?, ?, '', '', NOW(), {$scoreValues}"
                        . UserScopedQuery::insertValuePrepared('words', $bindings)
                    . ")";

                Connection::preparedExecute($sql, $bindings);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        return $max;
    }

    /**
     * Get newly created words after bulk insert.
     *
     * @param int $maxWoId The max word ID before insertion
     *
     * @return array<int, array<string, mixed>> Array of rows with id, text_lc, status, translation
     */
    public function getNewWordsAfter(int $maxWoId): array
    {
        $bindings = [$maxWoId];
        return Connection::preparedFetchAll(
            "SELECT id, text_lc, status, translation
             FROM words
             WHERE id > ?"
             . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings
        );
    }
}
