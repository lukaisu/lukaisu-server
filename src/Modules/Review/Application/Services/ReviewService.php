<?php

/**
 * Review Service - Business logic for word review operations
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Review\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Review\Application\Services;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Text\Application\Services\SentenceService;
use Lukaisu\Modules\Vocabulary\Application\Services\ExportService;
use Lukaisu\Modules\Vocabulary\Application\Services\TermStatusService;
use Lukaisu\Modules\Tags\Application\TagsFacade;
use Lukaisu\Modules\Review\Infrastructure\SessionStateManager;

/**
 * Service class for managing word reviews.
 *
 * Handles test SQL generation, word selection, status updates,
 * and progress tracking for vocabulary testing.
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Review\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class ReviewService
{
    /**
     * Sentence service instance
     *
     * @var SentenceService
     */
    private SentenceService $sentenceService;

    /**
     * Session state manager instance
     *
     * @var SessionStateManager
     */
    private SessionStateManager $sessionManager;

    /**
     * Constructor - initialize dependencies.
     *
     * @param SentenceService|null      $sentenceService Sentence service (optional)
     * @param SessionStateManager|null  $sessionManager  Session state manager (optional)
     */
    public function __construct(
        ?SentenceService $sentenceService = null,
        ?SessionStateManager $sessionManager = null
    ) {
        $this->sentenceService = $sentenceService ?? new SentenceService();
        $this->sessionManager = $sessionManager ?? new SessionStateManager();
    }

    /**
     * Get test identifier from request parameters.
     *
     * @param int|null    $selection    Test is of type selection
     * @param string|null $sessTestsql  SQL string for test
     * @param int|null    $lang         Test is of type language
     * @param int|null    $text         Testing text with ID $text
     *
     * @return array{0: string, 1: int|int[]|string} Selector type and selection value
     */
    public function getReviewIdentifier(
        ?int $selection,
        ?string $sessTestsql,
        ?int $lang,
        ?int $text
    ): array {
        if ($selection !== null && $sessTestsql !== null) {
            $dataStringArray = explode(",", trim($sessTestsql, "()"));
            $dataIntArray = array_map('intval', $dataStringArray);

            switch ($selection) {
                case 2:
                    return ['words', $dataIntArray];
                case 3:
                    return ['texts', $dataIntArray];
                default:
                    // Unknown selection type — reject rather than passing raw SQL
                    return ['', ''];
            }
        }

        if ($lang !== null) {
            return ['lang', $lang];
        }

        if ($text !== null) {
            return ['text', $text];
        }

        return ['', ''];
    }

    /**
     * Get SQL projection for test with prepared statement parameters.
     *
     * @param string    $selector  Type of test ('words', 'texts', 'lang', 'text')
     * @param int|int[] $selection Selection value
     *
     * @return array{sql: string, params: array<int, int>} SQL projection and bound params
     *
     * @throws \InvalidArgumentException If selector is invalid
     */
    public function getReviewSql(string $selector, int|array $selection): array
    {
        switch ($selector) {
            case 'words':
                $ids = is_array($selection) ? $selection : [$selection];
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                /** @var array<int, int> $params */
                $params = array_values(array_map('intval', $ids));
                return ['sql' => " words WHERE words.id IN ($placeholders) ", 'params' => $params];
            case 'texts':
                $ids = is_array($selection) ? $selection : [$selection];
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                /** @var array<int, int> $params */
                $params = array_values(array_map('intval', $ids));
                return [
                    'sql' => ' words, word_occurrences WHERE words.language_id = word_occurrences.language_id'
                        . ' AND word_occurrences.word_id = words.id'
                        . " AND word_occurrences.text_id IN ($placeholders) ",
                    'params' => $params
                ];
            case 'lang':
                $langId = is_array($selection) ? ($selection[0] ?? 0) : $selection;
                return ['sql' => " words WHERE language_id = ? ", 'params' => [$langId]];
            case 'text':
                $textId = is_array($selection) ? ($selection[0] ?? 0) : $selection;
                return [
                    'sql' => " words, word_occurrences WHERE words.language_id = word_occurrences.language_id"
                        . " AND word_occurrences.word_id = words.id AND word_occurrences.text_id = ? ",
                    'params' => [$textId]
                ];
            default:
                throw new \InvalidArgumentException(
                    "Invalid selector '$selector': must be 'words', 'texts', 'lang', or 'text'"
                );
        }
    }

    /**
     * Validate test selection (check single language).
     *
     * @param string             $reviewsql SQL projection string with ? placeholders
     * @param array<int, int>    $params    Bound parameters for the SQL
     *
     * @return array{valid: bool, langCount: int, error: string|null}
     */
    public function validateReviewSelection(string $reviewsql, array $params = []): array
    {
        $langCount = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT words.language_id) AS cnt FROM $reviewsql",
            $params,
            'cnt'
        );

        if ($langCount > 1) {
            return [
                'valid' => false,
                'langCount' => $langCount,
                'error' => "The selected terms are in $langCount languages, " .
                    "but tests are only possible in one language at a time."
            ];
        }

        return [
            'valid' => true,
            'langCount' => $langCount,
            'error' => null
        ];
    }

    /**
     * Get language name for test.
     *
     * @param int|null    $lang      Language ID
     * @param int|null    $text      Text ID
     * @param int|null    $selection Selection type
     * @param string|null $reviewsql   Test SQL for selection
     *
     * @return string Language name or 'L2' as default
     */
    public function getL2LanguageName(
        ?int $lang,
        ?int $text,
        ?int $selection = null,
        ?string $reviewsql = null
    ): string {
        if ($lang !== null) {
            /** @var mixed $nameRaw */
            $nameRaw = QueryBuilder::table('languages')
                ->where('id', '=', $lang)
                ->valuePrepared('name');
            return is_string($nameRaw) ? $nameRaw : 'L2';
        }

        if ($text !== null) {
            $row = QueryBuilder::table('texts')
                ->select(['languages.name'])
                ->join('languages', 'texts.language_id', '=', 'languages.id')
                ->where('texts.id', '=', $text)
                ->firstPrepared();
            /** @var mixed $nameRawFromRow */
            $nameRawFromRow = $row['name'] ?? null;
            return is_string($nameRawFromRow) ? $nameRawFromRow : 'L2';
        }

        if ($selection !== null && $reviewsql !== null) {
            $result = $this->buildSelectionReviewSql($selection, $reviewsql);
            if ($result !== null) {
                $validation = $this->validateReviewSelection($result['sql'], $result['params']);
                if ($validation['langCount'] == 1) {
                    $bindings = [];
                    $userScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
                    /** @var mixed $nameRawFromQuery */
                    $nameRawFromQuery = Connection::preparedFetchValue(
                        "SELECT languages.name
                        FROM languages, {$result['sql']} AND languages.id = words.language_id"
                        . $userScope . "
                        LIMIT 1",
                        array_merge($result['params'], $bindings),
                        'name'
                    );
                    return is_string($nameRawFromQuery) ? $nameRawFromQuery : 'L2';
                }
            }
        }

        return 'L2';
    }

    /**
     * Build test SQL from selection with prepared statement parameters.
     *
     * @param int    $selectionType Selection type (2=words, 3=texts)
     * @param string $selectionData Comma-separated IDs
     *
     * @return array{sql: string, params: array<int, int>}|null SQL and params, or null
     */
    public function buildSelectionReviewSql(int $selectionType, string $selectionData): ?array
    {
        $dataStringArray = explode(",", trim($selectionData, "()"));
        $dataIntArray = array_map('intval', $dataStringArray);
        switch ($selectionType) {
            case 2:
                return $this->getReviewSql('words', $dataIntArray);
            case 3:
                return $this->getReviewSql('texts', $dataIntArray);
            default:
                return null;
        }
    }

    /**
     * Get test counts (due and total).
     *
     * @param string          $reviewsql SQL projection string with ? placeholders
     * @param array<int, int|string> $params    Bound parameters for the SQL
     *
     * @return array{due: int, total: int}
     */
    public function getReviewCounts(string $reviewsql, array $params = []): array
    {
        $due = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT id) AS cnt
            FROM $reviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*' AND today_score < 0",
            $params,
            'cnt'
        );

        $total = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT id) AS cnt
            FROM $reviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*'",
            $params,
            'cnt'
        );

        return ['due' => $due, 'total' => $total];
    }

    /**
     * Get tomorrow's test count.
     *
     * @param string          $reviewsql SQL projection string with ? placeholders
     * @param array<int, int|string> $params    Bound parameters for the SQL
     *
     * @return int Number of tests due tomorrow
     */
    public function getTomorrowReviewCount(string $reviewsql, array $params = []): int
    {
        return (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT id) AS cnt
            FROM $reviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*' AND tomorrow_score < 0",
            $params,
            'cnt'
        );
    }

    /**
     * Get the next word to test.
     *
     * @param string          $reviewsql SQL projection string with ? placeholders
     * @param array<int, int|string> $params    Bound parameters for the SQL
     *
     * @return array|null Word record or null if none available
     */
    public function getNextWord(string $reviewsql, array $params = []): ?array
    {
        $pass = 0;
        while ($pass < 2) {
            $pass++;
            $sql = "SELECT DISTINCT words.id, words.text, text_lc, translation,
                romanization, sentence, words.language_id,
                (IFNULL(sentence, '') NOT LIKE CONCAT('%{', words.text, '}%')) AS notvalid,
                status,
                DATEDIFF(NOW(), status_changed_at) AS Days, today_score AS Score
                FROM $reviewsql AND status BETWEEN 1 AND 5
                AND translation != '' AND translation != '*' AND today_score < 0 " .
                ($pass == 1 ? 'AND random > RAND()' : '') . '
                ORDER BY today_score, random
                LIMIT 1';

            $rows = Connection::preparedFetchAll($sql, $params);
            $record = $rows[0] ?? null;

            if ($record !== null) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Get sentence containing the word for testing.
     *
     * @param int    $wordId Word ID
     * @param string $wordlc Lowercase word text
     *
     * @return array{sentence: string|null, found: bool}
     */
    public function getSentenceForWord(int $wordId, string $wordlc): array
    {
        // Find sentence with at least 70% known words
        // This is a complex query with subqueries - using raw SQL
        // word_occurrences inherits user context via text_id -> texts FK, so no user_id needed
        $sql = "SELECT DISTINCT ti.sentence_id AS id,
            1 - IFNULL(sUnknownCount.c, 0) / sWordCount.c AS KnownRatio
            FROM word_occurrences ti
            JOIN (
                SELECT t.sentence_id, COUNT(*) AS c
                FROM word_occurrences t
                WHERE t.word_count = 1
                GROUP BY t.sentence_id
            ) AS sWordCount ON sWordCount.sentence_id = ti.sentence_id
            LEFT JOIN (
                SELECT t.sentence_id, COUNT(*) AS c
                FROM word_occurrences t
                WHERE t.word_count = 1 AND t.word_id IS NULL
                GROUP BY t.sentence_id
            ) AS sUnknownCount ON sUnknownCount.sentence_id = ti.sentence_id
            WHERE ti.word_id = ?
            ORDER BY KnownRatio < 0.7, RAND()
            LIMIT 1";

        $rows = Connection::preparedFetchAll($sql, [$wordId]);
        $record = $rows[0] ?? null;

        if ($record === null) {
            return ['sentence' => null, 'found' => false];
        }

        $seid = (int) $record['id'];
        $sentenceCount = (int) Settings::getWithDefault('set-test-sentence-count');
        list($_, $sentence) = $this->sentenceService->formatSentence($seid, $wordlc, $sentenceCount);

        return ['sentence' => $sentence, 'found' => true];
    }

    /**
     * Get language settings for test display.
     *
     * @param int $langId Language ID
     *
     * @return array Language settings
     */
    public function getLanguageSettings(int $langId): array
    {
        $record = QueryBuilder::table('languages')
            ->select(['name', 'dict1_uri', 'dict2_uri', 'google_translate_uri',
                'text_size', 'remove_spaces', 'regexp_word_characters', 'right_to_left',
                'tts_voice_api'])
            ->where('id', '=', $langId)
            ->firstPrepared();

        if ($record === null) {
            return [];
        }

        return [
            'name' => $record['name'],
            'dict1Uri' => $record['dict1_uri'] ?? '',
            'dict2Uri' => $record['dict2_uri'] ?? '',
            'translateUri' => $record['google_translate_uri'] ?? '',
            'textSize' => (int) $record['text_size'],
            'removeSpaces' => (bool) $record['remove_spaces'],
            'regexWord' => $record['regexp_word_characters'],
            'rtl' => (bool) $record['right_to_left'],
            'ttsVoiceApi' => $record['tts_voice_api'] ?? null
        ];
    }

    /**
     * Get the language ID from test SQL.
     *
     * @param string          $reviewsql Test SQL projection with ? placeholders
     * @param array<int, int|string> $params    Bound parameters for the SQL
     *
     * @return int|null Language ID or null
     */
    public function getLanguageIdFromReviewSql(string $reviewsql, array $params = []): ?int
    {
        /** @var mixed $langIdRaw */
        $langIdRaw = Connection::preparedFetchValue(
            "SELECT words.language_id FROM $reviewsql LIMIT 1",
            $params,
            'language_id'
        );
        return is_numeric($langIdRaw) ? (int) $langIdRaw : null;
    }

    /**
     * Update word status during test.
     *
     * @param int $wordId    Word ID
     * @param int $newStatus New status (1-5)
     *
     * @return array{oldStatus: int, newStatus: int, oldScore: int, newScore: int}
     */
    public function updateWordStatus(int $wordId, int $newStatus): array
    {
        $oldStatus = (int) QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('status');

        $oldScore = (int) QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('GREATEST(0, ROUND(today_score, 0))');

        // Complex UPDATE with dynamic score calculation
        Connection::preparedExecute(
            "UPDATE words
            SET status = ?, status_changed_at = NOW(), " .
            TermStatusService::makeScoreRandomInsertUpdate('u') . "
            WHERE id = ?",
            [$newStatus, $wordId]
        );

        $newScore = (int) QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('GREATEST(0, ROUND(today_score, 0))');

        return [
            'oldStatus' => $oldStatus,
            'newStatus' => $newStatus,
            'oldScore' => $oldScore,
            'newScore' => $newScore
        ];
    }

    /**
     * Calculate new status based on status change direction.
     *
     * @param int $oldStatus Current status
     * @param int $change    Change amount (+1 or -1)
     *
     * @return int New status (clamped to 1-5)
     */
    public function calculateNewStatus(int $oldStatus, int $change): int
    {
        $newStatus = $oldStatus + $change;
        return max(1, min(5, $newStatus));
    }

    /**
     * Calculate status change direction.
     *
     * @param int $oldStatus Old status
     * @param int $newStatus New status
     *
     * @return int -1, 0, or 1
     */
    public function calculateStatusChange(int $oldStatus, int $newStatus): int
    {
        $diff = $newStatus - $oldStatus;
        if ($diff < 0) {
            return -1;
        }
        if ($diff > 0) {
            return 1;
        }
        return 0;
    }

    /**
     * Get word text by ID.
     *
     * @param int $wordId Word ID
     *
     * @return string|null Word text or null
     */
    public function getWordText(int $wordId): ?string
    {
        /** @var mixed $textRaw */
        $textRaw = QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('text');
        return is_string($textRaw) ? $textRaw : null;
    }

    /**
     * Clamp test type to valid range.
     *
     * @param int $testType Raw test type
     *
     * @return int Test type clamped to 1-5
     */
    public function clampReviewType(int $testType): int
    {
        return max(1, min(5, $testType));
    }

    /**
     * Check if test type is word mode (no sentence).
     *
     * @param int $testType Test type
     *
     * @return bool True if word mode (type > 3)
     */
    public function isWordMode(int $testType): bool
    {
        return $testType > 3;
    }

    /**
     * Get base test type (removes word mode offset).
     *
     * @param int $testType Test type
     *
     * @return int Base test type (1-3)
     */
    public function getBaseReviewType(int $testType): int
    {
        return $testType > 3 ? $testType - 3 : $testType;
    }

    /**
     * Get table test settings.
     *
     * @return array{edit: int, status: int, term: int, trans: int, rom: int, sentence: int}
     */
    public function getTableReviewSettings(): array
    {
        return [
            'edit' => Settings::getZeroOrOne('currenttabletestsetting1', 1),
            'status' => Settings::getZeroOrOne('currenttabletestsetting2', 1),
            'term' => Settings::getZeroOrOne('currenttabletestsetting3', 0),
            'trans' => Settings::getZeroOrOne('currenttabletestsetting4', 1),
            'rom' => Settings::getZeroOrOne('currenttabletestsetting5', 0),
            'sentence' => Settings::getZeroOrOne('currenttabletestsetting6', 1)
        ];
    }

    /**
     * Get words for table test.
     *
     * @param string          $reviewsql SQL projection string with ? placeholders
     * @param array<int, int|string> $params    Bound parameters for the SQL
     *
     * @return array<int, array<string, mixed>> Query results as array
     */
    public function getTableReviewWords(string $reviewsql, array $params = []): array
    {
        $sql = "SELECT DISTINCT words.id, words.text, translation, romanization,
            sentence, status, today_score AS Score
            FROM $reviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*'
            ORDER BY today_score, random * RAND()";

        return Connection::preparedFetchAll($sql, $params);
    }

    /**
     * Get test data from request parameters.
     *
     * @param int|null    $selection    Selection type
     * @param string|null $sessTestsql  Session test SQL
     * @param int|null    $langId       Language ID
     * @param int|null    $textId       Text ID
     *
     * @return array{title: string, property: string, reviewsql: string, reviewParams: array<int, int>,
     *     counts: array{due: int, total: int}}|null
     */
    public function getReviewDataFromParams(
        ?int $selection,
        ?string $sessTestsql,
        ?int $langId,
        ?int $textId
    ): ?array {
        $title = '';
        $property = '';
        $reviewsql = '';
        /** @var array<int, int> $reviewParams */
        $reviewParams = [];

        if ($selection !== null && $sessTestsql !== null) {
            $property = "selection=$selection";
            $result = $this->buildSelectionReviewSql($selection, $sessTestsql);

            if ($result === null) {
                return null;
            }
            $reviewsql = $result['sql'];
            $reviewParams = $result['params'];

            $validation = $this->validateReviewSelection($reviewsql, $reviewParams);
            if (!$validation['valid']) {
                return null;
            }

            $bindings = [];
            $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
            $totalCount = (int) Connection::preparedFetchValue(
                "SELECT COUNT(DISTINCT id) AS cnt FROM $reviewsql" . $userScope,
                array_merge($reviewParams, $bindings),
                'cnt'
            );
            $title = 'Selected ' . $totalCount . ' Term' . ($totalCount < 2 ? '' : 's');

            $bindings2 = [];
            $userScope2 = UserScopedQuery::forTablePrepared('words', $bindings2, 'words');
            /** @var mixed $langNameRaw */
            $langNameRaw = Connection::preparedFetchValue(
                "SELECT languages.name
                FROM languages, {$reviewsql} AND languages.id = words.language_id"
                . $userScope2 . "
                LIMIT 1",
                array_merge($reviewParams, $bindings2),
                'name'
            );
            $langName = is_string($langNameRaw) ? $langNameRaw : null;
            if ($langName !== null && $langName !== '') {
                $title .= ' IN ' . $langName;
            }
        } elseif ($langId !== null) {
            $property = "lang=$langId";
            $reviewsql = " words WHERE language_id = ? ";
            $reviewParams = [$langId];

            /** @var mixed $langNameRawFromLang */
            $langNameRawFromLang = QueryBuilder::table('languages')
                ->where('id', '=', $langId)
                ->valuePrepared('name');
            $langName = is_string($langNameRawFromLang) ? $langNameRawFromLang : 'Unknown';
            $title = "All Terms in " . $langName;
        } elseif ($textId !== null) {
            $property = "text=$textId";
            $reviewsql = " words, word_occurrences WHERE words.language_id = word_occurrences.language_id"
                . " AND word_occurrences.word_id = words.id AND word_occurrences.text_id = ? ";
            $reviewParams = [$textId];

            /** @var mixed $titleRaw */
            $titleRaw = QueryBuilder::table('texts')
                ->where('id', '=', $textId)
                ->valuePrepared('title');
            $title = is_string($titleRaw) ? $titleRaw : 'Unknown Text';

            Settings::savePerUser('currenttext', (string) $textId);
        } else {
            return null;
        }

        $counts = $this->getReviewCounts($reviewsql, $reviewParams);

        return [
            'title' => $title,
            'property' => $property,
            'reviewsql' => $reviewsql,
            'reviewParams' => $reviewParams,
            'counts' => $counts
        ];
    }

    /**
     * Update session progress after test.
     *
     * @param int $statusChange Status change direction (-1, 0, or 1)
     *
     * @return array{total: int, wrong: int, correct: int, remaining: int}
     */
    public function updateSessionProgress(int $statusChange): array
    {
        $sessionData = $this->sessionManager->getRawSessionData();
        $total = $sessionData['total'];
        $wrong = $sessionData['wrong'];
        $correct = $sessionData['correct'];
        $remaining = $total - $correct - $wrong;

        if ($remaining > 0) {
            $isCorrect = $statusChange >= 0;
            $this->sessionManager->recordAnswer($isCorrect);

            if ($isCorrect) {
                $correct++;
            } else {
                $wrong++;
            }
            $remaining--;
        }

        return [
            'total' => $total,
            'wrong' => $wrong,
            'correct' => $correct,
            'remaining' => $remaining
        ];
    }

    /**
     * Initialize review session.
     *
     * @param int $totalDue Total words due for review
     *
     * @return void
     */
    public function initializeReviewSession(int $totalDue): void
    {
        $session = $this->sessionManager->getSession();
        if ($session !== null) {
            // Update existing session with new total
            $newSession = new \Lukaisu\Modules\Review\Domain\ReviewSession(
                time() + 2,
                $totalDue,
                0,
                0
            );
            $this->sessionManager->saveSession($newSession);
        } else {
            // Create new session
            $newSession = new \Lukaisu\Modules\Review\Domain\ReviewSession(
                time() + 2,
                $totalDue,
                0,
                0
            );
            $this->sessionManager->saveSession($newSession);
        }
    }

    /**
     * Get review session data.
     *
     * @return array{start: int, correct: int, wrong: int, total: int}
     */
    public function getReviewSessionData(): array
    {
        $data = $this->sessionManager->getRawSessionData();
        return [
            'start' => $data['start'],
            'correct' => $data['correct'],
            'wrong' => $data['wrong'],
            'total' => $data['total']
        ];
    }

    /**
     * Get test solution text.
     *
     * @param int                  $testType Test type (1-5)
     * @param array<string, mixed> $wordData Word record data
     * @param bool                 $wordMode Whether in word mode (no sentence)
     * @param string               $wordText Word text for display
     *
     * @return string Solution text
     */
    public function getTestSolution(
        int $testType,
        array $wordData,
        bool $wordMode,
        string $wordText
    ): string {
        $baseType = $this->getBaseReviewType($testType);

        if ($baseType == 1) {
            $tagList = TagsFacade::getWordTagList((int) $wordData['id'], false);
            $tagFormatted = $tagList !== '' ? ' [' . $tagList . ']' : '';
            $translation = isset($wordData['translation']) && is_string($wordData['translation'])
                ? $wordData['translation']
                : '';
            $trans = ExportService::replaceTabNewline($translation) . $tagFormatted;
            return $wordMode ? $trans : "[$trans]";
        }

        return $wordText;
    }

    /**
     * Get waiting time setting.
     *
     * @return int Waiting time in milliseconds
     */
    public function getWaitingTime(): int
    {
        return (int) Settings::getWithDefault('set-test-main-frame-waiting-time');
    }

    /**
     * Get edit frame waiting time setting.
     *
     * @return int Waiting time in milliseconds
     */
    public function getEditFrameWaitingTime(): int
    {
        return (int) Settings::getWithDefault('set-test-edit-frame-waiting-time');
    }
}
