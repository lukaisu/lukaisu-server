<?php

/**
 * Word List Service - Facade for word list/edit operations
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\Services;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\DB;
use Lukaisu\Shared\Infrastructure\Database\Maintenance;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Language\Application\LanguageFacade;

/**
 * Facade for managing word list operations.
 *
 * Delegates filtering to WordListFilterBuilder, queries to WordListQueryService,
 * and export SQL building to WordListExportBuilder. Retains bulk operations,
 * single-word CRUD, and form data methods directly.
 *
 * @category   Lukaisu
 * @package    Lukaisu\Modules\Vocabulary\Application\Services
 * @author     HugoFara <hugo.farajallah@protonmail.com>
 * @license    Unlicense <http://unlicense.org/>
 * @link       https://hugofara.github.io/lukaisu-server/developer/api
 */
class WordListService
{
    private WordListFilterBuilder $filterBuilder;
    private WordListQueryService $queryService;
    private WordListExportBuilder $exportBuilder;
    private ExportService $exportService;

    public function __construct(
        ?WordListFilterBuilder $filterBuilder = null,
        ?WordListQueryService $queryService = null,
        ?WordListExportBuilder $exportBuilder = null,
        ?ExportService $exportService = null
    ) {
        $this->filterBuilder = $filterBuilder ?? new WordListFilterBuilder();
        $this->queryService = $queryService ?? new WordListQueryService();
        $this->exportBuilder = $exportBuilder ?? new WordListExportBuilder();
        $this->exportService = $exportService ?? new ExportService();
    }

    /**
     * Build the export file content for a set of marked term ids.
     *
     * The words list "Export" actions used to POST the marked ids to the native
     * /words page, which streamed a download. Under the headless cut (Phase R)
     * the export moved to POST /api/v1/terms/export, which returns the file body
     * so the bundled client can trigger a Blob download; this method produces that
     * body. The requested ids are first narrowed to the ones the current user
     * actually owns (see {@see filterOwnedWordIds}) so a caller cannot export
     * another user's terms; the raw export SQL then only ever sees owned ids.
     *
     * @param int[]  $ids    Marked term ids from the request
     * @param string $format Export format: 'anki', 'tsv' or 'flexible'
     *
     * @return string The export file content ('' when nothing is exportable)
     */
    public function exportMarkedTerms(array $ids, string $format): string
    {
        $ownedIds = $this->filterOwnedWordIds($ids);
        if (empty($ownedIds)) {
            return '';
        }

        switch ($format) {
            case 'tsv':
                $query = $this->exportBuilder->getTsvExportSql($ownedIds, '', '', '', '', '', []);
                return $this->exportService->generateTsvContent($query['sql'], $query['params']);
            case 'flexible':
                $query = $this->exportBuilder->getFlexibleExportSql($ownedIds, '', '', '', '', '', []);
                return $this->exportService->generateFlexibleContent($query['sql'], $query['params']);
            case 'anki':
            default:
                $query = $this->exportBuilder->getAnkiExportSql($ownedIds, '', '', '', '', '', []);
                return $this->exportService->generateAnkiContent($query['sql'], $query['params']);
        }
    }

    // =========================================================================
    // Delegated filter methods (WordListFilterBuilder)
    // =========================================================================

    /**
     * Build query condition for language filter.
     *
     * @param string     $langId Language ID
     * @param array|null &$params Optional: Reference to params array for prepared statements
     *
     * @return string SQL condition
     */
    public function buildLangCondition(string $langId, ?array &$params = null): string
    {
        return $this->filterBuilder->buildLangCondition($langId, $params);
    }

    /**
     * Build query condition for status filter.
     *
     * @param string $status Status code
     *
     * @return string SQL condition
     */
    public function buildStatusCondition(string $status): string
    {
        return $this->filterBuilder->buildStatusCondition($status);
    }

    /**
     * Build query condition for search query with prepared statement parameters.
     *
     * @param string     $query     Search query
     * @param string     $queryMode Query mode (term, rom, transl, etc.)
     * @param string     $regexMode Regex mode ('' or 'r')
     * @param array|null &$params   Optional: Reference to params array for prepared statements
     *
     * @return string SQL condition
     */
    public function buildQueryCondition(
        string $query,
        string $queryMode,
        string $regexMode,
        ?array &$params = null
    ): string {
        return $this->filterBuilder->buildQueryCondition($query, $queryMode, $regexMode, $params);
    }

    /**
     * Validate a regex pattern.
     *
     * @param string $pattern The regex pattern to validate
     *
     * @return bool True if valid, false otherwise
     */
    public function validateRegexPattern(string $pattern): bool
    {
        return $this->filterBuilder->validateRegexPattern($pattern);
    }

    /**
     * Build tag filter condition.
     *
     * @param string     $tag1   First tag ID (must be numeric or empty)
     * @param string     $tag2   Second tag ID (must be numeric or empty)
     * @param string     $tag12  Tag logic (0=OR, 1=AND)
     * @param array|null &$params Optional: Reference to params array for prepared statements
     *
     * @return string SQL HAVING clause
     */
    public function buildTagCondition(string $tag1, string $tag2, string $tag12, ?array &$params = null): string
    {
        return $this->filterBuilder->buildTagCondition($tag1, $tag2, $tag12, $params);
    }

    // =========================================================================
    // Delegated query methods (WordListQueryService)
    // =========================================================================

    /**
     * Count words matching the filter criteria.
     *
     * @param string $textId  Text ID filter (comma-separated IDs or empty)
     * @param string $whLang  Language condition (with ? placeholders)
     * @param string $whStat  Status condition
     * @param string $whQuery Query condition (with ? placeholders)
     * @param string $whTag   Tag condition (with ? placeholders)
     * @param array  $params  Merged binding parameters for filters
     *
     * @return int Number of matching words
     */
    public function countWords(
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $params = []
    ): int {
        return $this->queryService->countWords($textId, $whLang, $whStat, $whQuery, $whTag, $params);
    }

    /**
     * Get words list for display.
     *
     * @param array{whLang?: string, whStat?: string, whQuery?: string,
     *               whTag?: string, textId?: string, params?: array} $filters Filter parameters
     * @param int   $sort    Sort column index
     * @param int   $page    Page number
     * @param int   $perPage Items per page
     *
     * @return array Array of word records
     */
    public function getWordsList(array $filters, int $sort, int $page, int $perPage): array
    {
        return $this->queryService->getWordsList($filters, $sort, $page, $perPage);
    }

    /**
     * Get word IDs matching filter criteria (for 'all' actions).
     *
     * @param string $textId  Text ID filter (comma-separated IDs or empty)
     * @param string $whLang  Language condition (with ? placeholders)
     * @param string $whStat  Status condition
     * @param string $whQuery Query condition (with ? placeholders)
     * @param string $whTag   Tag condition (with ? placeholders)
     * @param array  $params  Merged binding parameters for filters
     *
     * @return int[] Array of word IDs
     */
    public function getFilteredWordIds(
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $params = []
    ): array {
        return $this->queryService->getFilteredWordIds($textId, $whLang, $whStat, $whQuery, $whTag, $params);
    }

    // =========================================================================
    // Delegated export methods (WordListExportBuilder)
    // =========================================================================

    /**
     * Get Anki export SQL for selected words.
     *
     * @param int[]  $ids          Array of word IDs (empty for filter-based export)
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getAnkiExportSql(
        array $ids,
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        return $this->exportBuilder->getAnkiExportSql($ids, $textId, $whLang, $whStat, $whQuery, $whTag, $filterParams);
    }

    /**
     * Get TSV export SQL for selected words.
     *
     * @param int[]  $ids          Array of word IDs (empty for filter-based export)
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getTsvExportSql(
        array $ids,
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        return $this->exportBuilder->getTsvExportSql($ids, $textId, $whLang, $whStat, $whQuery, $whTag, $filterParams);
    }

    /**
     * Get flexible export SQL for selected words.
     *
     * @param int[]  $ids          Array of word IDs (empty for filter-based export)
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getFlexibleExportSql(
        array $ids,
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        return $this->exportBuilder->getFlexibleExportSql(
            $ids,
            $textId,
            $whLang,
            $whStat,
            $whQuery,
            $whTag,
            $filterParams
        );
    }

    /**
     * Get test SQL for selected words.
     *
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getTestWordIdsSql(
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        return $this->exportBuilder->getTestWordIdsSql($textId, $whLang, $whStat, $whQuery, $whTag, $filterParams);
    }

    // =========================================================================
    // Bulk operations (kept in facade)
    // =========================================================================

    /**
     * Delete multiple words by ID list.
     *
     * @param int[] $ids Array of word IDs
     *
     * @return string Result message
     */
    public function deleteByIdList(array $ids): string
    {
        // Restrict to IDs the current user actually owns. Without this gate
        // an intruder can pass another user's WoIDs and wipe their words and
        // multi-word occurrences (word_occurrences has no user_id column).
        $ownedIds = $this->filterOwnedWordIds($ids);
        if (empty($ownedIds)) {
            return "Deleted";
        }

        DB::beginTransaction();
        try {
            // Delete multi-word text items first (before word deletion triggers FK SET NULL)
            $bindings = [];
            $inClause = Connection::buildPreparedInClause($ownedIds, $bindings);
            Connection::preparedExecute(
                'DELETE FROM word_occurrences
                WHERE word_count > 1 AND word_id in ' . $inClause,
                $bindings
            );

            // Delete words - FK constraints handle:
            // - Single-word word_occurrences.word_id set to NULL (ON DELETE SET NULL)
            // - word_tag_map deleted (ON DELETE CASCADE)
            $bindings2 = [];
            $inClause2 = Connection::buildPreparedInClause($ownedIds, $bindings2);
            Connection::preparedExecute(
                'DELETE FROM words WHERE id in ' . $inClause2,
                $bindings2
            );

            Maintenance::adjustAutoIncrement('words', 'id');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        return "Deleted";
    }

    /**
     * Pre-filter a list of WoIDs to those owned by the current user.
     *
     * In single-user mode the input list is returned unchanged. In multi-user
     * mode this issues a single SELECT scoped via {@see UserScopedQuery} and
     * keeps only the matching rows; foreign IDs are silently dropped so
     * downstream bulk SQL can run without a separate WHERE clause for the
     * user_id column (the IN clause shape stays simple).
     *
     * @param int[] $ids Word IDs from the request
     *
     * @return int[] Owned IDs (subset of $ids)
     */
    private function filterOwnedWordIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($ids, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
        if ($userScope === '') {
            return array_values(array_map('intval', $ids));
        }
        $rows = Connection::preparedFetchAll(
            'SELECT id FROM words WHERE id in ' . $inClause . $userScope,
            $bindings
        );
        $owned = [];
        foreach ($rows as $row) {
            $owned[] = (int) $row['id'];
        }
        return $owned;
    }

    /**
     * Update status for words in ID list.
     *
     * @param int[]  $ids        Array of word IDs
     * @param int    $newStatus  New status value
     * @param bool   $relative   If true, change by +1 or -1
     * @param string $actionType Type of action (spl1, smi1, s5, s1, s99, s98)
     *
     * @return string Result message
     */
    public function updateStatusByIdList(array $ids, int $newStatus, bool $relative, string $actionType): string
    {
        $scoreUpdate = TermStatusService::makeScoreRandomInsertUpdate('u');

        if ($relative && $newStatus > 0) {
            // Status +1
            $bindings = [];
            $inClause = Connection::buildPreparedInClause($ids, $bindings);
            $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
            Connection::preparedExecute(
                'update words
                set status=status+1, status_changed_at = NOW(),' . $scoreUpdate . '
                where status in (1,2,3,4) and id in ' . $inClause . $userScope,
                $bindings
            );
            return "Updated Status (+1)";
        } elseif ($relative && $newStatus < 0) {
            // Status -1
            $bindings = [];
            $inClause = Connection::buildPreparedInClause($ids, $bindings);
            $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
            Connection::preparedExecute(
                'update words
                set status=status-1, status_changed_at = NOW(),' . $scoreUpdate . '
                where status in (2,3,4,5) and id in ' . $inClause . $userScope,
                $bindings
            );
            return "Updated Status (-1)";
        }

        // Absolute status
        $bindings = [$newStatus];
        $inClause = Connection::buildPreparedInClause($ids, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
        Connection::preparedExecute(
            'update words
            set status=?, status_changed_at = NOW(),' . $scoreUpdate . '
            where id in ' . $inClause . $userScope,
            $bindings
        );
        return "Updated Status (=" . $newStatus . ")";
    }

    /**
     * Update status date to NOW for words in ID list.
     *
     * @param int[] $ids Array of word IDs
     *
     * @return string Result message
     */
    public function updateStatusDateByIdList(array $ids): string
    {
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($ids, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);

        Connection::preparedExecute(
            'update words
            set status_changed_at = NOW(),' . TermStatusService::makeScoreRandomInsertUpdate('u') . '
            where id in ' . $inClause . $userScope,
            $bindings
        );
        return "Updated Status Date (= Now)";
    }

    /**
     * Delete sentences for words in ID list.
     *
     * @param int[] $ids Array of word IDs
     *
     * @return string Result message
     */
    public function deleteSentencesByIdList(array $ids): string
    {
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($ids, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);

        Connection::preparedExecute(
            'update words
            set sentence = NULL
            where id in ' . $inClause . $userScope,
            $bindings
        );
        return "Term Sentence(s) deleted";
    }

    /**
     * Convert words to lowercase in ID list.
     *
     * @param int[] $ids Array of word IDs
     *
     * @return string Result message
     */
    public function toLowercaseByIdList(array $ids): string
    {
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($ids, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);

        Connection::preparedExecute(
            'update words
            set text = text_lc
            where id in ' . $inClause . $userScope,
            $bindings
        );
        return "Term(s) set to lowercase";
    }

    /**
     * Capitalize words in ID list.
     *
     * @param int[] $ids Array of word IDs
     *
     * @return string Result message
     */
    public function capitalizeByIdList(array $ids): string
    {
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($ids, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);

        Connection::preparedExecute(
            'update words
            set text = CONCAT(
                UPPER(LEFT(text_lc,1)),SUBSTRING(text_lc,2)
            )
            where id in ' . $inClause . $userScope,
            $bindings
        );
        return "Term(s) capitalized";
    }

    // =========================================================================
    // Single-word operations (kept in facade)
    // =========================================================================

    /**
     * Delete a single word by ID.
     *
     * @param int $wordId Word ID
     *
     * @return void
     */
    public function deleteSingleWord(int $wordId): void
    {
        // Delete multi-word text items first (before word deletion triggers FK SET NULL)
        Connection::preparedExecute(
            'DELETE FROM word_occurrences WHERE word_count > 1 AND word_id = ?',
            [$wordId]
        );

        // Delete word - FK constraints handle:
        // - Single-word word_occurrences.word_id set to NULL (ON DELETE SET NULL)
        // - word_tag_map deleted (ON DELETE CASCADE)
        $bindings = [$wordId];
        Connection::preparedExecute(
            'DELETE FROM words WHERE id = ?'
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings
        );

        Maintenance::adjustAutoIncrement('words', 'id');
    }

    // =========================================================================
    // Form data methods (kept in facade)
    // =========================================================================

    /**
     * Get word data for new term form.
     *
     * @param int $langId Language ID
     *
     * @return array Language data for form
     */
    public function getNewTermFormData(int $langId): array
    {
        $bindings = [$langId];
        $showRoman = (bool) Connection::preparedFetchValue(
            'SELECT show_romanization FROM languages WHERE id = ?'
            . UserScopedQuery::forTablePrepared('languages', $bindings),
            $bindings,
            'show_romanization'
        );

        $languageService = new LanguageFacade();
        return [
            'showRoman' => $showRoman,
            'scrdir' => $languageService->getScriptDirectionTag($langId),
        ];
    }

    /**
     * Get word data for edit form.
     *
     * @param int $wordId Word ID
     *
     * @return array|null Word data for form or null if not found
     */
    public function getEditFormData(int $wordId): ?array
    {
        $bindings = [$wordId];
        $record = Connection::preparedFetchOne(
            'SELECT words.id, words.language_id, words.text, words.text_lc, words.translation,
                words.romanization, words.sentence, words.status,
                languages.name, languages.right_to_left, languages.show_romanization
            FROM words, languages
            WHERE languages.id = words.language_id AND words.id = ?'
            . UserScopedQuery::forTablePrepared('words', $bindings, 'words')
            . UserScopedQuery::forTablePrepared('languages', $bindings, 'languages'),
            $bindings
        );

        if ($record === null) {
            return null;
        }

        $transl = ExportService::replaceTabNewline((string)$record['translation']);
        if ($transl == '*') {
            $transl = '';
        }

        return [
            'id' => $record['id'],
            'language_id' => $record['language_id'],
            'text' => $record['text'],
            'text_lc' => $record['text_lc'],
            'translation' => $transl,
            'romanization' => $record['romanization'],
            'sentence' => ExportService::replaceTabNewline((string)($record['sentence'] ?? '')),
            'status' => $record['status'],
            'name' => $record['name'],
            'right_to_left' => $record['right_to_left'],
            'show_romanization' => $record['show_romanization'],
            'scrdir' => $record['right_to_left'] ? ' dir="rtl" ' : '',
        ];
    }

    /**
     * Save a new word.
     *
     * @param array<string, mixed> $data Form data
     *
     * @return int Word ID of the created word
     *
     * @throws \RuntimeException If word could not be saved
     */
    public function saveNewWord(array $data): int
    {
        $translation = ExportService::replaceTabNewline((string)($data['translation'] ?? ''));
        if ($translation == '') {
            $translation = '*';
        }

        $textLc = mb_strtolower((string)$data["text"], 'UTF-8');
        $sentence = ExportService::replaceTabNewline((string)($data["sentence"] ?? ''));
        $romanization = (string)($data["romanization"] ?? '');

        $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

        $bindings = [
            (int)$data["language_id"], $textLc, (string)$data["text"],
            (int)$data["status"], $translation, $sentence, $romanization
        ];
        $sql = "INSERT INTO words (language_id, text_lc, text, "
            . "status, translation, sentence, romanization, status_changed_at, {$scoreColumns}"
            . UserScopedQuery::insertColumn('words')
            . ") VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), {$scoreValues}"
            . UserScopedQuery::insertValuePrepared('words', $bindings)
            . ")";

        $wid = Connection::preparedInsert($sql, $bindings);

        if ($wid <= 0) {
            throw new \RuntimeException('Failed to save word');
        }

        Maintenance::initWordCount();
        $bindings = [$wid];
        $len = (int)Connection::preparedFetchValue(
            'SELECT word_count FROM words WHERE id = ?'
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'word_count'
        );
        if ($len > 1) {
            (new ExpressionService())->insertExpressions($textLc, (int)$data["language_id"], (int)$wid, $len, 1);
        } else {
            Connection::preparedExecute(
                'UPDATE word_occurrences
                SET word_id = ?
                WHERE language_id = ? AND LOWER(text) = ?',
                [$wid, (int)$data["language_id"], $textLc]
            );
        }

        return (int) $wid;
    }

    /**
     * Update an existing word.
     *
     * @param array<string, mixed> $data Form data
     *
     * @return string Result message
     */
    public function updateWord(array $data): string
    {
        $translation = ExportService::replaceTabNewline((string)($data['translation'] ?? ''));
        if ($translation == '') {
            $translation = '*';
        }

        $textLc = mb_strtolower((string)$data["text"], 'UTF-8');
        $sentence = ExportService::replaceTabNewline((string)($data["sentence"] ?? ''));
        $romanization = (string)($data["romanization"] ?? '');

        $oldstatus = (int)$data["WoOldStatus"];
        $newstatus = (int)$data["status"];

        if ($oldstatus != $newstatus) {
            $bindings = [
                (string)$data["text"], $textLc, $translation, $sentence,
                $romanization, $newstatus, (int)$data["id"]
            ];
            $affected = Connection::preparedExecute(
                'UPDATE words
                SET text = ?, text_lc = ?, translation = ?, sentence = ?,
                    romanization = ?, status = ?, status_changed_at = NOW(),' .
                TermStatusService::makeScoreRandomInsertUpdate('u') .
                ' WHERE id = ?'
                . UserScopedQuery::forTablePrepared('words', $bindings),
                $bindings
            );
        } else {
            $bindings = [(string)$data["text"], $textLc, $translation, $sentence, $romanization, (int)$data["id"]];
            $affected = Connection::preparedExecute(
                'UPDATE words
                SET text = ?, text_lc = ?, translation = ?, sentence = ?,
                    romanization = ?,' .
                TermStatusService::makeScoreRandomInsertUpdate('u') .
                ' WHERE id = ?'
                . UserScopedQuery::forTablePrepared('words', $bindings),
                $bindings
            );
        }

        return $affected > 0 ? "Updated" : "No changes made";
    }
}
