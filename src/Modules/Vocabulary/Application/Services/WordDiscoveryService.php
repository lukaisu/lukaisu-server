<?php

/**
 * Word Discovery Service - Finding and creating unknown words
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

use Lukaisu\Shared\Infrastructure\Utilities\StringUtils;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\Escaping;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Service for discovering and creating unknown words.
 *
 * Handles:
 * - Finding unknown words in texts
 * - Quick word creation with status
 * - Hover-based word creation
 * - Bulk word status operations
 *
 * @since 3.0.0
 */
class WordDiscoveryService
{
    private WordContextService $contextService;
    private WordLinkingService $linkingService;

    /**
     * Constructor.
     *
     * @param WordContextService|null $contextService Context service
     * @param WordLinkingService|null $linkingService Linking service
     */
    public function __construct(
        ?WordContextService $contextService = null,
        ?WordLinkingService $linkingService = null
    ) {
        $this->contextService = $contextService ?? new WordContextService();
        $this->linkingService = $linkingService ?? new WordLinkingService();
    }

    /**
     * Get unknown words in a text (words without a id).
     *
     * @param int $textId Text ID
     *
     * @return array<int, array<string, mixed>> Array of rows with text and Ti2TextLC columns
     */
    public function getUnknownWordsInText(int $textId): array
    {
        // word_occurrences inherits user context via text_id -> texts FK.
        // words is LEFT JOINed; the user-scope must live in the JOIN ON
        // clause, otherwise WHERE id IS NULL would still match other
        // users' words (and a flat AND in WHERE would block the IS-NULL).
        $joinScopeBindings = [];
        $joinScope = UserScopedQuery::forTablePrepared('words', $joinScopeBindings, 'words');
        $bindings = array_merge($joinScopeBindings, [$textId]);
        return Connection::preparedFetchAll(
            "SELECT DISTINCT text, LOWER(text) AS Ti2TextLC
             FROM (word_occurrences LEFT JOIN words
                   ON LOWER(text) = text_lc AND language_id = language_id{$joinScope})
             WHERE id IS NULL AND word_count = 1 AND text_id = ?
             ORDER BY position",
            $bindings
        );
    }

    /**
     * Get all unknown words in a text (words without a id).
     *
     * @param int $textId Text ID
     *
     * @return array<int, array<string, mixed>> Array of rows with text and Ti2TextLC columns
     */
    public function getAllUnknownWordsInText(int $textId): array
    {
        // Use word_id IS NULL to match how the reading interface identifies
        // unknown words (joined via word_id = id). This avoids disagreement
        // with the text-based join when a word was learned in another text
        // but word_id was not yet updated in this text.
        $bindings = [$textId];
        return Connection::preparedFetchAll(
            "SELECT DISTINCT text, LOWER(text) AS Ti2TextLC
             FROM word_occurrences
             WHERE word_id IS NULL AND word_count = 1 AND text_id = ?
             ORDER BY position",
            $bindings
        );
    }

    /**
     * Get unknown words for bulk translation with pagination.
     *
     * @param int $textId Text ID
     * @param int $offset Starting position
     * @param int $limit  Number of words to return
     *
     * @return array<int, array<string, mixed>> Array of rows with word, language_id, pos columns
     */
    public function getUnknownWordsForBulkTranslate(
        int $textId,
        int $offset,
        int $limit
    ): array {
        // word_occurrences inherits user context via text_id -> texts FK
        return Connection::preparedFetchAll(
            "SELECT text AS word, language_id, MIN(position) AS pos
             FROM word_occurrences
             WHERE word_id IS NULL AND text_id = ? AND word_count = 1
             GROUP BY LOWER(text)
             ORDER BY pos
             LIMIT ?, ?",
            [$textId, $offset, $limit]
        );
    }

    /**
     * Create a word with a specific status.
     *
     * @param int    $langId Language ID
     * @param string $term   The term text
     * @param string $termlc Lowercase version of the term
     * @param int    $status Status to set (98=ignored, 99=well-known)
     *
     * @return array{id: int, rows: int} Word ID and number of inserted rows
     */
    public function createWithStatus(int $langId, string $term, string $termlc, int $status): array
    {
        // Check if already exists
        $bindings = [$termlc];
        /** @var int|null $existingId */
        $existingId = Connection::preparedFetchValue(
            "SELECT id FROM words WHERE text_lc = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'id'
        );

        if ($existingId !== null) {
            return ['id' => $existingId, 'rows' => 0];
        }

        $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

        $bindings = [$langId, $term, $termlc, $status];
        $sql = "INSERT INTO words (
                language_id, text, text_lc, status, status_changed_at, {$scoreColumns}"
                . UserScopedQuery::insertColumn('words')
            . ") VALUES (?, ?, ?, ?, NOW(), {$scoreValues}"
                . UserScopedQuery::insertValuePrepared('words', $bindings)
            . ")";

        $wid = Connection::preparedInsert($sql, $bindings);
        return ['id' => (int)$wid, 'rows' => 1];
    }

    /**
     * Insert a word with a specific status and link to text items.
     *
     * Used for quick insert operations (mark as known/ignored).
     *
     * @param int    $textId Text ID (to get language)
     * @param string $term   Word text
     * @param int    $status Status (98=ignored, 99=well-known)
     *
     * @return array{id: int, term: string, termlc: string, hex: string}
     */
    public function insertWordWithStatus(int $textId, string $term, int $status): array
    {
        $termlc = mb_strtolower($term, 'UTF-8');
        $langId = $this->contextService->getLanguageIdFromText($textId);

        if ($langId === null) {
            throw new \RuntimeException("Text ID $textId not found");
        }

        $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

        $bindings = [$langId, $term, $termlc, $status];
        $sql = "INSERT INTO words (
                language_id, text, text_lc, status, word_count, status_changed_at, {$scoreColumns}"
                . UserScopedQuery::insertColumn('words')
            . ") VALUES (?, ?, ?, ?, 1, NOW(), {$scoreValues}"
                . UserScopedQuery::insertValuePrepared('words', $bindings)
            . ")";

        $wid = (int) Connection::preparedInsert($sql, $bindings);

        // Link to text items
        $this->linkingService->linkToTextItems($wid, $langId, $termlc);

        return [
            'id' => $wid,
            'term' => $term,
            'termlc' => $termlc,
            'hex' => StringUtils::toClassName($termlc)
        ];
    }

    /**
     * Create a word on hover with optional translation.
     *
     * Used when user hovers and clicks to set a word status directly from the text.
     *
     * @param int    $textId      Text ID
     * @param string $text        Word text
     * @param int    $status      Word status (1-5)
     * @param string $translation Optional translation
     *
     * @return array{
     *     wid: int,
     *     word: string,
     *     wordRaw: string,
     *     translation: string,
     *     status: int,
     *     hex: string
     * }
     */
    public function createOnHover(
        int $textId,
        string $text,
        int $status,
        string $translation = '*'
    ): array {
        $wordlc = mb_strtolower($text, 'UTF-8');

        $langId = $this->contextService->getLanguageIdFromText($textId);
        if ($langId === null) {
            throw new \RuntimeException("Text ID $textId not found");
        }

        $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

        $bindings = [$langId, $wordlc, $text, $status, $translation];
        $sql = "INSERT INTO words (
                language_id, text_lc, text, status, translation, sentence,
                romanization, status_changed_at, {$scoreColumns}"
                . UserScopedQuery::insertColumn('words')
            . ") VALUES (?, ?, ?, ?, ?, '', '', NOW(), {$scoreValues}"
                . UserScopedQuery::insertValuePrepared('words', $bindings)
            . ")";

        $wid = (int) Connection::preparedInsert($sql, $bindings);

        // Link to text items
        $this->linkingService->linkToTextItems($wid, $langId, $wordlc);

        $hex = StringUtils::toClassName(
            Escaping::prepareTextdata($wordlc)
        );

        return [
            'wid' => $wid,
            'word' => $text,
            'wordRaw' => $text,
            'translation' => $translation,
            'status' => $status,
            'hex' => $hex
        ];
    }

    /**
     * Process a single word for the "mark all as well-known" operation.
     *
     * @param int    $status New word status
     * @param string $term   Word text
     * @param string $termlc Lowercase word text
     * @param int    $langId Language ID
     *
     * @return array{int, array{wid: int, hex: string, term: string, status: int}|null} Rows modified and word data
     */
    public function processWordForWellKnown(int $status, string $term, string $termlc, int $langId): array
    {
        $bindings = [$termlc, $langId];
        $wid = Connection::preparedFetchValue(
            "SELECT id FROM words WHERE text_lc = ? AND language_id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'id'
        );

        if ($wid !== null) {
            // Word already exists — update its status
            $scoreUpdate = TermStatusService::makeScoreRandomInsertUpdate('u');
            /** @var list<int|string> $updateBindings */
            $updateBindings = [$status, (int) $wid];
            Connection::preparedExecute(
                "UPDATE words SET status = ?, status_changed_at = NOW(), {$scoreUpdate} WHERE id = ?"
                . UserScopedQuery::forTablePrepared('words', $updateBindings),
                $updateBindings
            );

            return [1, [
                'wid' => (int) $wid,
                'hex' => StringUtils::toClassName($termlc),
                'term' => $term,
                'status' => $status
            ]];
        }

        try {
            $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
            $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

            /** @var list<int|string> $bindings */
            $bindings = [$langId, $term, $termlc, $status];
            $sql = "INSERT INTO words (
                    language_id, text, text_lc, status, status_changed_at, {$scoreColumns}"
                    . UserScopedQuery::insertColumn('words')
                . ") VALUES (?, ?, ?, ?, NOW(), {$scoreValues}"
                    . UserScopedQuery::insertValuePrepared('words', $bindings)
                . ")";

            $stmt = Connection::prepare($sql);
            $stmt->bindValues($bindings);
            $rows = $stmt->execute();
            $wid = (int) $stmt->insertId();

            if ($rows == 0) {
                \Lukaisu\Shared\UI\Helpers\PageLayoutHelper::renderMessage(
                    "WARNING: No rows modified!",
                    false
                );
            }

            $wordData = [
                'wid' => $wid,
                'hex' => StringUtils::toClassName($termlc),
                'term' => $term,
                'status' => $status
            ];

            return [$rows, $wordData];
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Could not modify words: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update word status.
     *
     * @param int $wordId Word ID
     * @param int $status New status (1-5, 98, 99)
     *
     * @return void
     */
    public function setStatus(int $wordId, int $status): void
    {
        $scoreUpdate = TermStatusService::makeScoreRandomInsertUpdate('u');
        $bindings = [$status, $wordId];
        $sql = "UPDATE words SET status = ?, status_changed_at = NOW(), {$scoreUpdate} WHERE id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings);
        Connection::preparedExecute($sql, $bindings);
    }

    /**
     * Mark all unknown words in a text with a specific status.
     *
     * @param int $textId Text ID
     * @param int $status Status to apply (98=ignored, 99=well-known)
     *
     * @return array{int, array<array{wid: int, hex: string, term: string, status: int}>} Total count and words data
     */
    public function markAllWordsWithStatus(int $textId, int $status): array
    {
        $langId = $this->contextService->getLanguageIdFromText($textId);
        if ($langId === null) {
            throw new \RuntimeException("Text ID $textId not found");
        }

        $wordsData = [];
        $count = 0;
        $records = $this->getAllUnknownWordsInText($textId);
        foreach ($records as $record) {
            list($modified_rows, $wordData) = $this->processWordForWellKnown(
                $status,
                (string) $record['text'],
                (string) $record['Ti2TextLC'],
                $langId
            );
            if ($wordData !== null) {
                $wordsData[] = $wordData;
            }
            $count += $modified_rows;
        }

        // Associate existing textitems
        $this->linkingService->linkAllTextItems();

        return array($count, $wordsData);
    }
}
