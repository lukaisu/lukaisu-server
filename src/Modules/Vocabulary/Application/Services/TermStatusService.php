<?php

/**
 * Term Status Service
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

use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermStatus;

/**
 * Service class for term status definitions and scoring.
 *
 * Contains status definitions, SQL formulas for scoring,
 * and utility methods for status management.
 */
class TermStatusService
{
    /**
     * Return an associative array of all possible statuses.
     *
     * Names are localized via the i18n translator (common.status_*).
     * Abbreviations are language-neutral: "1".."5" for learning levels,
     * empty string for 98/99 — display code should fall back to the
     * full localized name when the abbreviation is empty.
     *
     * @return array<int, array{abbr: string, name: string}>
     *         Keys are 1, 2, 3, 4, 5, 98, 99.
     */
    public static function getStatuses(): array
    {
        $learning  = self::translateStatus('common.status_learning', 'Learning');
        $learned   = self::translateStatus('common.status_learned', 'Learned');
        $wellKnown = self::translateStatus('common.status_well_known', 'Well Known');
        $ignored   = self::translateStatus('common.status_ignored', 'Ignored');
        return [
            TermStatus::NEW         => ["abbr" => "1", "name" => $learning],
            TermStatus::LEARNING_2  => ["abbr" => "2", "name" => $learning],
            TermStatus::LEARNING_3  => ["abbr" => "3", "name" => $learning],
            TermStatus::LEARNING_4  => ["abbr" => "4", "name" => $learning],
            TermStatus::LEARNED     => ["abbr" => "5", "name" => $learned],
            TermStatus::WELL_KNOWN  => ["abbr" => "",  "name" => $wellKnown],
            TermStatus::IGNORED     => ["abbr" => "",  "name" => $ignored],
        ];
    }

    /**
     * Resolve a status translation key, falling back to the English label
     * when the translator is unavailable (e.g. during a Container reset).
     */
    private static function translateStatus(string $key, string $fallback): string
    {
        $value = __($key);
        return ($value === $key) ? $fallback : $value;
    }

    /**
     * SQL expressions that seed FSRS scheduling state from `status` +
     * `status_changed_at` (issue #238). Mirrors the client `fsrsForStatus`
     * (`src/frontend/js/shared/offline/local/fsrs.ts`): status 1 => a fresh New
     * card due now; 2-5 => a Review card seeded so the *derived* status matches
     * (`statusFromStability`); 98/99 => unscheduled. Referencing the row's own
     * columns means these only work in `INSERT ... SELECT` / `UPDATE` contexts —
     * the same constraint the old score formulas had. Keep the seed stabilities
     * in sync with `STATUS_SEED_STABILITY` in `fsrs.ts` and the migration.
     */
    private const FSRS_SEED_STABILITY =
        'CASE `status` WHEN 1 THEN 0 WHEN 2 THEN 3 WHEN 3 THEN 15 WHEN 4 THEN 60 WHEN 5 THEN 120 ELSE 0 END';
    private const FSRS_SEED_DIFFICULTY =
        'CASE WHEN `status` BETWEEN 2 AND 5 THEN 5 ELSE 0 END';
    private const FSRS_SEED_DUE =
        'CASE `status`'
        . ' WHEN 1 THEN status_changed_at'
        . ' WHEN 2 THEN status_changed_at + INTERVAL 3 DAY'
        . ' WHEN 3 THEN status_changed_at + INTERVAL 15 DAY'
        . ' WHEN 4 THEN status_changed_at + INTERVAL 60 DAY'
        . ' WHEN 5 THEN status_changed_at + INTERVAL 120 DAY'
        . ' ELSE status_changed_at END';
    private const FSRS_SEED_LAST_REVIEW =
        'CASE WHEN `status` BETWEEN 2 AND 5 THEN status_changed_at ELSE NULL END';
    private const FSRS_SEED_REPS =
        'CASE WHEN `status` BETWEEN 2 AND 5 THEN 1 ELSE 0 END';
    private const FSRS_SEED_STATE =
        'CASE WHEN `status` BETWEEN 2 AND 5 THEN 2 ELSE 0 END';

    /**
     * Generate the SQL fragment that seeds FSRS scheduling columns in
     * INSERT/UPDATE statements (issue #238, Phase 2). Kept under the historical
     * name and `iv`/`id`/`u` contract so the ~20 word write paths don't change;
     * it now writes the FSRS columns instead of the retired Leitner scores.
     *
     * @param 'iv'|'id'|'u'|string $type Fragment type:
     *                                   - 'iv': column names for INSERT
     *                                   - 'id': seed values for INSERT ... SELECT
     *                                   - 'u': SET clause for UPDATE
     *
     * @return string SQL code fragment
     */
    public static function makeScoreRandomInsertUpdate(string $type): string
    {
        return match ($type) {
            'iv' => ' stability, difficulty, due_at, last_reviewed_at, reps, lapses, fsrs_state ',
            'id' => ' ' . self::FSRS_SEED_STABILITY . ', ' . self::FSRS_SEED_DIFFICULTY . ', '
                . self::FSRS_SEED_DUE . ', ' . self::FSRS_SEED_LAST_REVIEW . ', '
                . self::FSRS_SEED_REPS . ', 0, ' . self::FSRS_SEED_STATE . ' ',
            'u' => ' stability = ' . self::FSRS_SEED_STABILITY
                . ', difficulty = ' . self::FSRS_SEED_DIFFICULTY
                . ', due_at = ' . self::FSRS_SEED_DUE
                . ', last_reviewed_at = ' . self::FSRS_SEED_LAST_REVIEW
                . ', reps = ' . self::FSRS_SEED_REPS
                . ', lapses = 0'
                . ', fsrs_state = ' . self::FSRS_SEED_STATE . ' ',
            default => '',
        };
    }

    /**
     * Check if a status is valid.
     *
     * @param int $status Status to check
     *
     * @return bool True if valid status
     */
    public static function isValidStatus(int $status): bool
    {
        return isset(self::getStatuses()[$status]);
    }

    /**
     * Get status name.
     *
     * @param int $status Status value
     *
     * @return string Status name or empty if not found
     */
    public static function getStatusName(int $status): string
    {
        $statuses = self::getStatuses();
        return $statuses[$status]['name'] ?? '';
    }

    /**
     * Get status abbreviation.
     *
     * @param int $status Status value
     *
     * @return string Status abbreviation or empty if not found
     */
    public static function getStatusAbbr(int $status): string
    {
        $statuses = self::getStatuses();
        return $statuses[$status]['abbr'] ?? '';
    }

    /**
     * Get the CSS color class for a status.
     *
     * @param int $status Status value
     *
     * @return string CSS color class name
     */
    public static function getStatusColor(int $status): string
    {
        return match ($status) {
            TermStatus::NEW => 'status1',
            TermStatus::LEARNING_2 => 'status2',
            TermStatus::LEARNING_3 => 'status3',
            TermStatus::LEARNING_4 => 'status4',
            TermStatus::LEARNED => 'status5',
            TermStatus::IGNORED => 'status98',
            TermStatus::WELL_KNOWN => 'status99',
            default => 'status0',
        };
    }

    /**
     * Get all learning statuses (1-5).
     *
     * @return int[]
     */
    public static function getLearningStatuses(): array
    {
        return [
            TermStatus::NEW,
            TermStatus::LEARNING_2,
            TermStatus::LEARNING_3,
            TermStatus::LEARNING_4,
            TermStatus::LEARNED,
        ];
    }

    /**
     * Get active learning statuses (1-4, not yet learned).
     *
     * @return int[]
     */
    public static function getActiveLearningStatuses(): array
    {
        return [
            TermStatus::NEW,
            TermStatus::LEARNING_2,
            TermStatus::LEARNING_3,
            TermStatus::LEARNING_4,
        ];
    }

    /**
     * Get known statuses (5 and 99).
     *
     * @return int[]
     */
    public static function getKnownStatuses(): array
    {
        return [
            TermStatus::LEARNED,
            TermStatus::WELL_KNOWN,
        ];
    }

    /**
     * Check if a status represents a learning term (not ignored, not well-known).
     *
     * @param int $status Status value
     *
     * @return bool
     */
    public static function isLearningStatus(int $status): bool
    {
        return $status >= 1 && $status <= 5;
    }

    /**
     * Check if a status represents a known term.
     *
     * @param int $status Status value
     *
     * @return bool
     */
    public static function isKnownStatus(int $status): bool
    {
        return $status === TermStatus::LEARNED || $status === TermStatus::WELL_KNOWN;
    }

    /**
     * Check if a status represents an ignored term.
     *
     * @param int $status Status value
     *
     * @return bool
     */
    public static function isIgnoredStatus(int $status): bool
    {
        return $status === TermStatus::IGNORED;
    }
}
