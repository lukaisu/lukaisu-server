<?php

/**
 * Multi-Word Service - Multi-word expression operations
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

use Lukaisu\Modules\Tags\Application\TagsFacade;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Shared\Infrastructure\Database\Maintenance;

/**
 * Service for managing multi-word expressions.
 *
 * Handles:
 * - Creating multi-word terms
 * - Updating multi-word terms
 * - Retrieving multi-word data
 * - Deleting multi-word expressions
 * - Finding multi-word by text
 *
 * @since 3.0.0
 */
class MultiWordService
{
    private ExpressionService $expressionService;

    /**
     * Constructor.
     *
     * @param ExpressionService|null $expressionService Expression service
     */
    public function __construct(?ExpressionService $expressionService = null)
    {
        $this->expressionService = $expressionService ?? new ExpressionService();
    }

    /**
     * Create a new multi-word expression.
     *
     * @param array<string, mixed> $data Multi-word data:
     *                    - lgid: Language ID
     *                    - textlc: Lowercase text
     *                    - text: Original text
     *                    - status: Word status
     *                    - translation: Translation text
     *                    - sentence: Example sentence
     *                    - notes: Personal notes
     *                    - roman: Romanization/phonetic
     *                    - wordcount: Number of words in expression
     *
     * @return array{id: int, message: string}
     */
    public function createMultiWord(array $data): array
    {
        $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

        $sentence = ExportService::replaceTabNewline((string) $data['sentence']);
        $notes = ExportService::replaceTabNewline((string) ($data['notes'] ?? ''));

        $bindings = [
            (int) $data['lgid'],
            $data['textlc'],
            $data['text'],
            (int) $data['status'],
            $data['translation'],
            $sentence,
            $notes,
            $data['roman'],
            (int) $data['wordcount']
        ];

        $sql = "INSERT INTO words (
                language_id, text_lc, text, status, translation, sentence,
                notes, romanization, word_count, status_changed_at, {$scoreColumns}"
                . UserScopedQuery::insertColumn('words')
            . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), {$scoreValues}"
                . UserScopedQuery::insertValuePrepared('words', $bindings)
            . ")";

        $wid = (int) Connection::preparedInsert($sql, $bindings);

        Maintenance::initWordCount();
        TagsFacade::saveWordTagsFromForm($wid);
        $this->expressionService->insertExpressions(
            (string) $data['textlc'],
            (int) $data['lgid'],
            $wid,
            (int) $data['wordcount'],
            0
        );

        return [
            'id' => $wid,
            'message' => __('vocabulary.flash.term_saved')
        ];
    }

    /**
     * Update an existing multi-word expression.
     *
     * @param int   $wordId    Word ID
     * @param array $data      Multi-word data (same keys as createMultiWord)
     * @param int   $oldStatus Previous status for comparison
     * @param int   $newStatus New status to set
     *
     * @return array{id: int, message: string, status: int}
     */
    public function updateMultiWord(int $wordId, array $data, int $oldStatus, int $newStatus): array
    {
        $scoreUpdate = TermStatusService::makeScoreRandomInsertUpdate('u');
        $sentence = ExportService::replaceTabNewline((string) $data['sentence']);
        $notes = ExportService::replaceTabNewline((string) ($data['notes'] ?? ''));

        if ($oldStatus != $newStatus) {
            // Status changed - update status and timestamp
            $bindings = [
                $data['text'],
                $data['translation'],
                $sentence,
                $notes,
                $data['roman'],
                $newStatus,
                $wordId
            ];
            $sql = "UPDATE words SET
                    text = ?, translation = ?, sentence = ?, notes = ?, romanization = ?,
                    status = ?, status_changed_at = NOW(), {$scoreUpdate}
                    WHERE id = ?"
                    . UserScopedQuery::forTablePrepared('words', $bindings);
            Connection::preparedExecute($sql, $bindings);
        } else {
            // Status unchanged
            $bindings = [
                $data['text'],
                $data['translation'],
                $sentence,
                $notes,
                $data['roman'],
                $wordId
            ];
            $sql = "UPDATE words SET
                    text = ?, translation = ?, sentence = ?, notes = ?, romanization = ?, {$scoreUpdate}
                    WHERE id = ?"
                    . UserScopedQuery::forTablePrepared('words', $bindings);
            Connection::preparedExecute($sql, $bindings);
        }

        TagsFacade::saveWordTagsFromForm($wordId);

        return [
            'id' => $wordId,
            'message' => __('vocabulary.flash.term_updated_short'),
            'status' => $newStatus
        ];
    }

    /**
     * Get multi-word data for editing.
     *
     * @param int $wordId Word ID
     *
     * @return array{
     *     text: string, lgid: int, translation: string, sentence: string,
     *     notes: string, romanization: string, status: int
     * }|null Multi-word data or null if not found
     */
    public function getMultiWordData(int $wordId): ?array
    {
        $bindings = [$wordId];
        $record = Connection::preparedFetchOne(
            "SELECT text, language_id, translation, sentence, notes, romanization, status
             FROM words WHERE id = ?"
             . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings
        );

        if ($record === null) {
            return null;
        }

        return [
            'text' => (string) $record['text'],
            'lgid' => (int) $record['language_id'],
            'translation' => ExportService::replaceTabNewline((string) $record['translation']),
            'sentence' => ExportService::replaceTabNewline((string) $record['sentence']),
            'notes' => ExportService::replaceTabNewline((string) ($record['notes'] ?? '')),
            'romanization' => (string) $record['romanization'],
            'status' => (int) $record['status']
        ];
    }

    /**
     * Delete a multi-word expression.
     *
     * Deletes the word and its associated text items with word count > 1.
     *
     * @param int $wordId Word ID to delete
     *
     * @return int Number of affected rows
     */
    public function deleteMultiWord(int $wordId): int
    {
        $result = QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->delete();

        Maintenance::adjustAutoIncrement('words', 'id');

        QueryBuilder::table('word_occurrences')
            ->where('word_count', '>', 1)
            ->where('word_id', '=', $wordId)
            ->delete();

        return $result;
    }

    /**
     * Find multi-word by text and language.
     *
     * @param string $textlc Lowercase text
     * @param int    $langId Language ID
     *
     * @return int|null Word ID or null if not found
     */
    public function findMultiWordByText(string $textlc, int $langId): ?int
    {
        $bindings = [$langId, $textlc];
        /** @var int|null $wid */
        $wid = Connection::preparedFetchValue(
            "SELECT id FROM words
             WHERE language_id = ? AND text_lc = ?"
             . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'id'
        );
        return $wid;
    }
}
