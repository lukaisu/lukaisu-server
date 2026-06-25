<?php

/**
 * MySQL Review Repository
 *
 * Infrastructure implementation for review/test persistence.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Review\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Review\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Review\Domain\ReviewRepositoryInterface;
use Lukaisu\Modules\Review\Domain\ReviewConfiguration;
use Lukaisu\Modules\Review\Domain\ReviewWord;
use Lukaisu\Modules\Text\Application\Services\SentenceService;
use Lukaisu\Modules\Vocabulary\Application\Services\TermStatusService;
use Lukaisu\Modules\Activity\Infrastructure\MySqlActivityRepository;

/**
 * MySQL implementation of ReviewRepositoryInterface.
 *
 * Handles all database operations for the Review module.
 *
 * @since 3.0.0
 */
class MySqlReviewRepository implements ReviewRepositoryInterface
{
    private SentenceService $sentenceService;
    private MySqlActivityRepository $activityRepository;

    /**
     * Constructor.
     *
     * @param SentenceService|null        $sentenceService    Sentence service (optional)
     * @param MySqlActivityRepository|null $activityRepository Activity repository (optional)
     */
    public function __construct(
        ?SentenceService $sentenceService = null,
        ?MySqlActivityRepository $activityRepository = null
    ) {
        $this->sentenceService = $sentenceService ?? new SentenceService();
        $this->activityRepository = $activityRepository ?? new MySqlActivityRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function findNextWordForReview(ReviewConfiguration $config): ?ReviewWord
    {
        // FSRS (issue #238): the next word is the most overdue learning term.
        $params = [];
        $reviewsql = $config->toSqlProjectionPrepared($params);
        // The FSRS columns travel with the word so the client can compute the
        // next card when graded (issue #238). due_at/last_reviewed_at are exposed
        // as epoch-second columns (UNIX_TIMESTAMP is the inverse of the
        // FROM_UNIXTIME used by gradeWord, so they round-trip across timezones).
        $sql = "SELECT DISTINCT id, text, text_lc, translation,
            romanization, sentence, language_id,
            (IFNULL(sentence, '') NOT LIKE CONCAT('%{', text, '}%')) AS notvalid,
            status,
            DATEDIFF(NOW(), status_changed_at) AS Days, stability AS Score,
            stability, difficulty, reps, lapses, fsrs_state,
            UNIX_TIMESTAMP(due_at) AS due_ts,
            UNIX_TIMESTAMP(last_reviewed_at) AS last_review_ts
            FROM $reviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*' AND due_at <= NOW()
            ORDER BY due_at, RAND()
            LIMIT 1";

        $rows = Connection::preparedFetchAll($sql, $params);
        $record = $rows[0] ?? null;

        return $record !== null ? ReviewWord::fromRecord($record) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSentenceForWord(int $wordId, string $wordLc): array
    {
        // Find sentence with at least 70% known words
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
        list($_, $sentence) = $this->sentenceService->formatSentence($seid, $wordLc, $sentenceCount);

        return ['sentence' => $sentence, 'found' => true];
    }

    /**
     * {@inheritdoc}
     */
    public function getReviewCounts(ReviewConfiguration $config): array
    {
        $dueParams = [];
        $dueReviewsql = $config->toSqlProjectionPrepared($dueParams);

        $due = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT id) AS cnt
            FROM $dueReviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*' AND due_at <= NOW()",
            $dueParams,
            'cnt'
        );

        $totalParams = [];
        $totalReviewsql = $config->toSqlProjectionPrepared($totalParams);

        $total = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT id) AS cnt
            FROM $totalReviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*'",
            $totalParams,
            'cnt'
        );

        return ['due' => $due, 'total' => $total];
    }

    /**
     * {@inheritdoc}
     */
    public function getTomorrowCount(ReviewConfiguration $config): int
    {
        $params = [];
        $reviewsql = $config->toSqlProjectionPrepared($params);

        return (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT id) AS cnt
            FROM $reviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*'
            AND due_at <= NOW() + INTERVAL 1 DAY",
            $params,
            'cnt'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTableWords(ReviewConfiguration $config): array
    {
        $params = [];
        $reviewsql = $config->toSqlProjectionPrepared($params);

        $sql = "SELECT DISTINCT id, text, text_lc, translation, romanization,
            sentence, language_id, status, stability AS Score,
            DATEDIFF(NOW(), status_changed_at) AS Days
            FROM $reviewsql AND status BETWEEN 1 AND 5
            AND translation != '' AND translation != '*'
            ORDER BY due_at, RAND()";

        $rows = Connection::preparedFetchAll($sql, $params);
        $words = [];

        foreach ($rows as $record) {
            $words[] = ReviewWord::fromRecord($record);
        }

        return $words;
    }

    /**
     * {@inheritdoc}
     */
    public function updateWordStatus(int $wordId, int $newStatus): array
    {
        $oldStatus = (int) QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('status');

        $oldScore = (int) QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('GREATEST(0, ROUND(stability, 0))');

        // Update with score recalculation
        $bindings = [$newStatus, $wordId];
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
        Connection::preparedExecute(
            "UPDATE words
            SET status = ?, status_changed_at = NOW(), " .
            TermStatusService::makeScoreRandomInsertUpdate('u') . "
            WHERE id = ?" . $userScope,
            $bindings
        );

        $this->activityRepository->incrementTermsReviewed();

        $newScore = (int) QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('GREATEST(0, ROUND(stability, 0))');

        return [
            'oldStatus' => $oldStatus,
            'newStatus' => $newStatus,
            'oldScore' => $oldScore,
            'newScore' => $newScore
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $card The client-computed FSRS card
     * @param array<string, mixed> $log  The review-log entry to record
     *
     * @return array{status: int, due: int}
     */
    public function gradeWord(int $wordId, int $status, array $card, array $log): array
    {
        // FSRS runs client-side; the server only stores the resulting card and
        // logs the review. Epoch-ms timestamps are converted with FROM_UNIXTIME
        // so they share the MySQL session timezone used by NOW().
        $dueSeconds = (float) ($card['due'] ?? 0) / 1000;
        $lastReviewSeconds = isset($card['lastReview']) && $card['lastReview'] !== null
            ? (float) $card['lastReview'] / 1000
            : null;

        $bindings = [
            $status,
            (float) ($card['stability'] ?? 0),
            (float) ($card['difficulty'] ?? 0),
            $dueSeconds,
            $lastReviewSeconds,
            (int) ($card['reps'] ?? 0),
            (int) ($card['lapses'] ?? 0),
            (int) ($card['state'] ?? 0),
            $wordId,
        ];
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
        Connection::preparedExecute(
            "UPDATE words
            SET status = ?, stability = ?, difficulty = ?,
                due_at = FROM_UNIXTIME(?), last_reviewed_at = FROM_UNIXTIME(?),
                reps = ?, lapses = ?, fsrs_state = ?
            WHERE id = ?" . $userScope,
            $bindings
        );

        Connection::preparedExecute(
            "INSERT INTO review_log
                (word_id, user_id, grade, fsrs_state, stability, difficulty,
                 elapsed_days, scheduled_days, reviewed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))",
            [
                $wordId,
                UserScopedQuery::getUserIdForInsert('words'),
                (int) ($log['grade'] ?? 0),
                (int) ($log['state'] ?? 0),
                (float) ($log['stability'] ?? 0),
                (float) ($log['difficulty'] ?? 0),
                (float) ($log['elapsedDays'] ?? 0),
                (float) ($log['scheduledDays'] ?? 0),
                (float) ($log['reviewedAt'] ?? 0) / 1000,
            ]
        );

        $this->activityRepository->incrementTermsReviewed();

        return ['status' => $status, 'due' => (int) ($card['due'] ?? 0)];
    }

    /**
     * {@inheritdoc}
     */
    public function getWordStatus(int $wordId): ?int
    {
        /** @var mixed $status */
        $status = QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('status');

        return $status !== null ? (int) $status : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguageSettings(int $langId): array
    {
        $record = QueryBuilder::table('languages')
            ->select([
                'name', 'dict1_uri', 'dict2_uri', 'google_translate_uri',
                'text_size', 'remove_spaces', 'regexp_word_characters',
                'right_to_left', 'tts_voice_api'
            ])
            ->where('id', '=', $langId)
            ->firstPrepared();

        if ($record === null) {
            return [
                'name' => '',
                'dict1Uri' => '',
                'dict2Uri' => '',
                'translateUri' => '',
                'textSize' => 100,
                'removeSpaces' => false,
                'regexWord' => '',
                'rtl' => false,
                'ttsVoiceApi' => null
            ];
        }

        /** @var mixed $nameVal */
        $nameVal = $record['name'] ?? '';
        /** @var mixed $regexVal */
        $regexVal = $record['regexp_word_characters'] ?? '';
        /** @var mixed $ttsVal */
        $ttsVal = $record['tts_voice_api'] ?? null;

        return [
            'name' => is_string($nameVal) ? $nameVal : '',
            'dict1Uri' => is_string($record['dict1_uri'] ?? '') ? (string) ($record['dict1_uri'] ?? '') : '',
            'dict2Uri' => is_string($record['dict2_uri'] ?? '') ? (string) ($record['dict2_uri'] ?? '') : '',
            'translateUri' => is_string($record['google_translate_uri'] ?? '')
                ? (string) ($record['google_translate_uri'] ?? '')
                : '',
            'textSize' => (int) $record['text_size'],
            'removeSpaces' => (bool) $record['remove_spaces'],
            'regexWord' => is_string($regexVal) ? $regexVal : '',
            'rtl' => (bool) $record['right_to_left'],
            'ttsVoiceApi' => is_string($ttsVal) ? $ttsVal : null
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguageIdFromConfig(ReviewConfiguration $config): ?int
    {
        $params = [];
        $reviewsql = $config->toSqlProjectionPrepared($params);

        /** @var mixed $langId */
        $langId = Connection::preparedFetchValue(
            "SELECT language_id FROM $reviewsql LIMIT 1",
            $params,
            'language_id'
        );

        return $langId !== null ? (int) $langId : null;
    }

    /**
     * {@inheritdoc}
     */
    public function validateSingleLanguage(ReviewConfiguration $config): array
    {
        $params = [];
        $reviewsql = $config->toSqlProjectionPrepared($params);

        $langCount = (int) Connection::preparedFetchValue(
            "SELECT COUNT(DISTINCT language_id) AS cnt FROM $reviewsql",
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
     * {@inheritdoc}
     */
    public function getLanguageName(ReviewConfiguration $config): string
    {
        if ($config->reviewKey === ReviewConfiguration::KEY_LANG) {
            /** @var mixed $name */
            $name = QueryBuilder::table('languages')
                ->where('id', '=', $config->selection)
                ->valuePrepared('name');
            return $name !== null ? (string) $name : 'L2';
        }

        if ($config->reviewKey === ReviewConfiguration::KEY_TEXT) {
            $row = QueryBuilder::table('texts')
                ->select(['name'])
                ->join('languages', 'language_id', '=', 'id')
                ->where('id', '=', $config->selection)
                ->firstPrepared();
            /** @var mixed $name */
            $name = $row['name'] ?? null;
            return is_string($name) ? $name : 'L2';
        }

        // For selection-based tests, get language from first word
        $validation = $this->validateSingleLanguage($config);

        if ($validation['langCount'] === 1) {
            $params = [];
            $reviewsql = $config->toSqlProjectionPrepared($params);
            $bindings = [];
            $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
            /** @var mixed $name */
            $name = Connection::preparedFetchValue(
                "SELECT name
                FROM languages, {$reviewsql} AND id = language_id"
                . $userScope . "
                LIMIT 1",
                array_merge($params, $bindings),
                'name'
            );
            return $name !== null ? (string) $name : 'L2';
        }

        return 'L2';
    }

    /**
     * {@inheritdoc}
     */
    public function getWordText(int $wordId): ?string
    {
        /** @var mixed $text */
        $text = QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->valuePrepared('text');

        return $text !== null ? (string) $text : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableReviewSettings(): array
    {
        return [
            'edit' => Settings::getZeroOrOne('currenttabletestsetting1', 1),
            'status' => Settings::getZeroOrOne('currenttabletestsetting2', 1),
            'term' => Settings::getZeroOrOne('currenttabletestsetting3', 0),
            'trans' => Settings::getZeroOrOne('currenttabletestsetting4', 1),
            'rom' => Settings::getZeroOrOne('currenttabletestsetting5', 0),
            'sentence' => Settings::getZeroOrOne('currenttabletestsetting6', 1),
            'contextRom' => Settings::getZeroOrOne('currenttabletestsetting7', 0),
            'contextTrans' => Settings::getZeroOrOne('currenttabletestsetting8', 0)
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @return array{
     *     sentence: string|null,
     *     sentenceId: int|null,
     *     found: bool,
     *     annotations: array<int, array{
     *         text: string,
     *         romanization: string|null,
     *         translation: string|null,
     *         isTarget: bool,
     *         order: int
     *     }>
     * }
     */
    public function getSentenceWithAnnotations(int $wordId, string $wordLc): array
    {
        // First, find the best sentence (same logic as getSentenceForWord)
        $sql = "SELECT DISTINCT ti.sentence_id AS id, ti.text_id AS id,
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
            return ['sentence' => null, 'sentenceId' => null, 'found' => false, 'annotations' => []];
        }

        $seid = (int) $record['id'];
        $txid = (int) $record['id'];
        $sentenceCount = (int) Settings::getWithDefault('set-test-sentence-count');

        // Get the formatted sentence
        list($_, $sentence) = $this->sentenceService->formatSentence($seid, $wordLc, $sentenceCount);

        // Now fetch all word annotations for this sentence (and surrounding sentences if mode > 1)
        $annotations = $this->fetchSentenceAnnotations($seid, $txid, $sentenceCount, $wordLc);

        return [
            'sentence' => $sentence,
            'sentenceId' => $seid,
            'found' => true,
            'annotations' => $annotations
        ];
    }

    /**
     * Fetch word annotations for a sentence and surrounding context.
     *
     * @param int    $seid          Main sentence ID
     * @param int    $txid          Text ID
     * @param int    $sentenceCount Number of sentences (1=current, 2=prev+current, 3=prev+current+next)
     * @param string $targetWordLc  Lowercase target word text
     *
     * @return array<int, array{
     *     text: string,
     *     romanization: string|null,
     *     translation: string|null,
     *     isTarget: bool,
     *     order: int
     * }>
     */
    private function fetchSentenceAnnotations(int $seid, int $txid, int $sentenceCount, string $targetWordLc): array
    {
        // Build list of sentence IDs to include
        $sentenceIds = [$seid];

        if ($sentenceCount > 1) {
            // Get previous sentence
            /** @var mixed $prevSeid */
            $prevSeid = Connection::preparedFetchValue(
                "SELECT id FROM sentences
                WHERE id < ? AND text_id = ?
                AND TRIM(text) NOT IN ('¶', '')
                ORDER BY id DESC LIMIT 1",
                [$seid, $txid],
                'id'
            );
            if ($prevSeid !== null) {
                array_unshift($sentenceIds, (int) $prevSeid);
            }
        }

        if ($sentenceCount > 2) {
            // Get next sentence
            /** @var mixed $nextSeid */
            $nextSeid = Connection::preparedFetchValue(
                "SELECT id FROM sentences
                WHERE id > ? AND text_id = ?
                AND TRIM(text) NOT IN ('¶', '')
                ORDER BY id ASC LIMIT 1",
                [$seid, $txid],
                'id'
            );
            if ($nextSeid !== null) {
                $sentenceIds[] = (int) $nextSeid;
            }
        }

        if (empty($sentenceIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sentenceIds), '?'));

        // Fetch all text items with their word data
        $sql = "SELECT ti.position, ti.text, ti.word_count, ti.word_id,
                w.text_lc, w.romanization, w.translation
            FROM word_occurrences ti
            LEFT JOIN words w ON ti.word_id = w.id
            WHERE ti.sentence_id IN ($placeholders) AND ti.word_count < 2
            AND ti.text != '¶'
            ORDER BY ti.position";

        $rows = Connection::preparedFetchAll($sql, $sentenceIds);

        $annotations = [];
        foreach ($rows as $row) {
            $order = (int) $row['position'];
            $text = (string) $row['text'];
            /** @var mixed $woId */
            $woId = $row['word_id'];
            $isTarget = mb_strtolower($text, 'UTF-8') === $targetWordLc;

            // Only include annotation data if the word is known (has a id)
            if ($woId !== null) {
                /** @var mixed $romanization */
                $romanization = $row['romanization'];
                $annotations[$order] = [
                    'text' => $text,
                    'romanization' => ($romanization === null || $romanization === '') ? null : (string)$romanization,
                    'translation' => $this->getFirstTranslation((string)($row['translation'] ?? '')),
                    'isTarget' => $isTarget,
                    'order' => $order
                ];
            } else {
                // Still track the position for non-word tokens (punctuation, etc.)
                $annotations[$order] = [
                    'text' => $text,
                    'romanization' => null,
                    'translation' => null,
                    'isTarget' => false,
                    'order' => $order
                ];
            }
        }

        return $annotations;
    }

    /**
     * Get the first translation from a translation string.
     *
     * @param string $trans Full translation string (may contain separators)
     *
     * @return string|null First translation only, or null if empty
     */
    private function getFirstTranslation(string $trans): ?string
    {
        if ($trans === '' || $trans === '*') {
            return null;
        }
        $arr = preg_split('/[' . \Lukaisu\Shared\Infrastructure\Utilities\StringUtils::getSeparators() . ']/u', $trans);
        if ($arr === false) {
            return null;
        }
        $r = trim($arr[0]);
        return $r !== '' && $r !== '*' ? $r : null;
    }
}
