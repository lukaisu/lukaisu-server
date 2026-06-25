<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Review\Infrastructure;

use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Modules\Review\Infrastructure\MySqlReviewRepository;
use Lukaisu\Modules\Review\Domain\ReviewConfiguration;
use Lukaisu\Modules\Text\Application\Services\SentenceService;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the FSRS scheduling paths of MySqlReviewRepository
 * (issue #238, Phase 2):
 *
 *  - due_at-based selection (findNextWordForReview / getReviewCounts /
 *    getTomorrowCount no longer use the legacy today_score/tomorrow_score);
 *  - gradeWord persisting the client-computed FSRS card and writing a
 *    review_log row.
 *
 * Requires the test database; skipped without one. All rows are scoped to a
 * dedicated throwaway language so counts are exact and cleanup is total
 * (review_log cascades on words delete).
 */
class MySqlReviewRepositoryFsrsTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $langId = 0;

    private MySqlReviewRepository $repository;

    public static function setUpBeforeClass(): void
    {
        $config = EnvLoader::getDatabaseConfig();
        $testDbname = 'test_' . $config['dbname'];

        if (!Globals::getDbConnection()) {
            try {
                $connection = Configuration::connect(
                    $config['server'],
                    $config['userid'],
                    $config['passwd'],
                    $testDbname,
                    $config['socket'] ?? ''
                );
                Globals::setDbConnection($connection);
                self::$dbConnected = true;
            } catch (\Exception $e) {
                self::$dbConnected = false;
            }
        } else {
            self::$dbConnected = true;
        }

        if (!self::$dbConnected) {
            return;
        }

        $existing = Connection::fetchValue(
            "SELECT id AS value FROM languages WHERE name = 'FsrsTestLanguage' LIMIT 1"
        );
        if ($existing) {
            self::$langId = (int) $existing;
        } else {
            Connection::query(
                "INSERT INTO languages (name, dict1_uri, dict2_uri, google_translate_uri, "
                . "text_size, character_substitutions, regexp_split_sentences, exceptions_split_sentences, "
                . "regexp_word_characters, remove_spaces, split_each_char, right_to_left, show_romanization) "
                . "VALUES ('FsrsTestLanguage', '', '', '', 100, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 1)"
            );
            self::$langId = (int) Connection::lastInsertId();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbConnected || self::$langId === 0) {
            return;
        }
        // review_log rows cascade away with their words (ON DELETE CASCADE).
        Connection::query("DELETE FROM words WHERE language_id = " . self::$langId);
        Connection::query("DELETE FROM languages WHERE id = " . self::$langId);
    }

    protected function setUp(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }
        // Each test starts from a clean slate for the throwaway language.
        Connection::query("DELETE FROM words WHERE language_id = " . self::$langId);
        $this->repository = new MySqlReviewRepository($this->createMock(SentenceService::class));
    }

    /**
     * Insert a learning word scoped to the test language.
     *
     * @param string $text      Term text (also used as text_lc; unique per test)
     * @param int    $status    Stored status (1-5)
     * @param string $dueExpr   SQL expression for due_at, e.g. "NOW() - INTERVAL 1 DAY"
     * @param float  $stability FSRS stability (days)
     *
     * @return int The new word id
     */
    private function insertWord(string $text, int $status, string $dueExpr, float $stability): int
    {
        Connection::query(
            "INSERT INTO words (language_id, text, text_lc, status, translation, "
            . "status_changed_at, due_at, stability) "
            . "VALUES (" . self::$langId . ", '{$text}', '{$text}', {$status}, 'trans_{$text}', "
            . "NOW(), {$dueExpr}, {$stability})"
        );
        return (int) Connection::lastInsertId();
    }

    // =========================================================================
    // gradeWord — persist the client-computed card + write a review_log row
    // =========================================================================

    public function testGradeWordPersistsCardAndLogsReview(): void
    {
        $wordId = $this->insertWord('grademe', 1, 'NOW()', 0.0);

        // Deterministic epoch-ms timestamps (FROM_UNIXTIME round-trips via
        // UNIX_TIMESTAMP regardless of session timezone).
        $reviewedAtSec = 1750000000;
        $dueSec = $reviewedAtSec + 10 * 86400; // due in ten days

        $card = [
            'stability' => 12.5,
            'difficulty' => 6.0,
            'due' => $dueSec * 1000,
            'lastReview' => $reviewedAtSec * 1000,
            'reps' => 1,
            'lapses' => 0,
            'state' => 2,
        ];
        $log = [
            'grade' => 3,
            'state' => 2,
            'stability' => 12.5,
            'difficulty' => 6.0,
            'elapsedDays' => 0.0,
            'scheduledDays' => 10.0,
            'reviewedAt' => $reviewedAtSec * 1000,
        ];

        // statusFromStability(12.5) === 3 on the client; the server just stores it.
        $result = $this->repository->gradeWord($wordId, 3, $card, $log);

        $this->assertSame(3, $result['status']);
        $this->assertSame($dueSec * 1000, $result['due']);

        $row = Connection::fetchOne(
            "SELECT status, stability, difficulty, reps, lapses, fsrs_state, "
            . "UNIX_TIMESTAMP(due_at) AS due_ts, UNIX_TIMESTAMP(last_reviewed_at) AS lr_ts "
            . "FROM words WHERE id = {$wordId}"
        );
        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row['status']);
        $this->assertEqualsWithDelta(12.5, (float) $row['stability'], 0.0001);
        $this->assertEqualsWithDelta(6.0, (float) $row['difficulty'], 0.0001);
        $this->assertSame(1, (int) $row['reps']);
        $this->assertSame(0, (int) $row['lapses']);
        $this->assertSame(2, (int) $row['fsrs_state']);
        $this->assertSame($dueSec, (int) $row['due_ts']);
        $this->assertSame($reviewedAtSec, (int) $row['lr_ts']);

        // Exactly one review_log row, carrying the grade and the snapshot.
        $logCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS value FROM review_log WHERE word_id = {$wordId}"
        );
        $this->assertSame(1, $logCount);

        $logRow = Connection::fetchOne(
            "SELECT grade, fsrs_state, stability, difficulty, elapsed_days, scheduled_days "
            . "FROM review_log WHERE word_id = {$wordId}"
        );
        $this->assertNotNull($logRow);
        $this->assertSame(3, (int) $logRow['grade']);
        $this->assertSame(2, (int) $logRow['fsrs_state']);
        $this->assertEqualsWithDelta(12.5, (float) $logRow['stability'], 0.0001);
        $this->assertEqualsWithDelta(6.0, (float) $logRow['difficulty'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $logRow['elapsed_days'], 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $logRow['scheduled_days'], 0.0001);
    }

    public function testGradeWordAcceptsNullLastReview(): void
    {
        $wordId = $this->insertWord('newcard', 1, 'NOW()', 0.0);

        $card = [
            'stability' => 0.5,
            'difficulty' => 5.0,
            'due' => 1750000000 * 1000,
            'lastReview' => null,
            'reps' => 0,
            'lapses' => 0,
            'state' => 0,
        ];
        $log = [
            'grade' => 1,
            'state' => 0,
            'stability' => 0.5,
            'difficulty' => 5.0,
            'elapsedDays' => 0.0,
            'scheduledDays' => 0.0,
            'reviewedAt' => 1750000000 * 1000,
        ];

        $this->repository->gradeWord($wordId, 1, $card, $log);

        $lr = Connection::fetchValue(
            "SELECT last_reviewed_at AS value FROM words WHERE id = {$wordId}"
        );
        $this->assertNull($lr);
    }

    // =========================================================================
    // due_at-based selection
    // =========================================================================

    public function testFindNextWordReturnsMostOverdueWithCard(): void
    {
        $overdueId = $this->insertWord('overdue', 2, 'NOW() - INTERVAL 1 DAY', 3.0);
        $this->insertWord('future', 2, 'NOW() + INTERVAL 10 DAY', 60.0);

        $word = $this->repository->findNextWordForReview(
            ReviewConfiguration::fromLanguage(self::$langId)
        );

        $this->assertNotNull($word);
        $this->assertSame($overdueId, $word->id);
        $this->assertSame(2, $word->status);
        // The FSRS card travels with the word so the client can grade it.
        $this->assertIsArray($word->fsrs);
        $this->assertEqualsWithDelta(3.0, (float) $word->fsrs['stability'], 0.0001);
    }

    public function testFindNextWordSkipsWordsDueInTheFuture(): void
    {
        $this->insertWord('notyet', 2, 'NOW() + INTERVAL 10 DAY', 60.0);

        $word = $this->repository->findNextWordForReview(
            ReviewConfiguration::fromLanguage(self::$langId)
        );

        $this->assertNull($word);
    }

    public function testGetReviewCountsCountsOnlyDueRows(): void
    {
        $this->insertWord('due_a', 2, 'NOW() - INTERVAL 1 HOUR', 3.0);
        $this->insertWord('due_b', 3, 'NOW() - INTERVAL 2 DAY', 15.0);
        $this->insertWord('later', 4, 'NOW() + INTERVAL 30 DAY', 60.0);

        $counts = $this->repository->getReviewCounts(
            ReviewConfiguration::fromLanguage(self::$langId)
        );

        $this->assertSame(2, $counts['due']);
        $this->assertSame(3, $counts['total']);
    }

    public function testGetTomorrowCountCountsWithinOneDayWindow(): void
    {
        $this->insertWord('soon', 2, 'NOW() + INTERVAL 2 HOUR', 3.0);
        $this->insertWord('alsodue', 2, 'NOW() - INTERVAL 1 HOUR', 3.0);
        $this->insertWord('faraway', 2, 'NOW() + INTERVAL 30 DAY', 60.0);

        $count = $this->repository->getTomorrowCount(
            ReviewConfiguration::fromLanguage(self::$langId)
        );

        // Everything due within the next day (overdue included), excluding the
        // far-future card.
        $this->assertSame(2, $count);
    }
}
