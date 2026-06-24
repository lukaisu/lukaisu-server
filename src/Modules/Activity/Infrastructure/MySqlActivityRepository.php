<?php

/**
 * MySQL Activity Repository
 *
 * PHP version 8.2
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Activity\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Activity\Infrastructure;

use Lukaisu\Modules\Activity\Domain\ActivityRepositoryInterface;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Shared\Infrastructure\Globals;

/**
 * MySQL implementation of ActivityRepositoryInterface.
 *
 * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic counter upserts.
 *
 * @since 3.0.0
 */
class MySqlActivityRepository implements ActivityRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function incrementTermsCreated(int $count = 1): void
    {
        $this->incrementColumn('terms_created', $count);
    }

    /**
     * {@inheritdoc}
     */
    public function incrementTermsReviewed(int $count = 1): void
    {
        $this->incrementColumn('terms_reviewed', $count);
    }

    /**
     * {@inheritdoc}
     */
    public function incrementTextsRead(int $count = 1): void
    {
        $this->incrementColumn('texts_read', $count);
    }

    /**
     * {@inheritdoc}
     */
    public function getActivityForDateRange(string $startDate, string $endDate): array
    {
        $bindings = [$startDate, $endDate];
        $userScope = UserScopedQuery::forTablePrepared('activity_log', $bindings);

        $tableName = Globals::table('activity_log');
        $rows = Connection::preparedFetchAll(
            "SELECT date, terms_created, terms_reviewed, texts_read
             FROM {$tableName}
             WHERE date BETWEEN ? AND ?{$userScope}
             ORDER BY date",
            $bindings
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'date' => (string) $row['date'],
                'terms_created' => (int) $row['terms_created'],
                'terms_reviewed' => (int) $row['terms_reviewed'],
                'texts_read' => (int) $row['texts_read'],
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveDatesDescending(): array
    {
        $bindings = [];
        $userScope = UserScopedQuery::forTablePrepared('activity_log', $bindings);

        $tableName = Globals::table('activity_log');
        $rows = Connection::preparedFetchAll(
            "SELECT date
             FROM {$tableName}
             WHERE (terms_created > 0 OR terms_reviewed > 0 OR texts_read > 0)
             {$userScope}
             ORDER BY date DESC",
            $bindings
        );

        $dates = [];
        foreach ($rows as $row) {
            $dates[] = (string) $row['date'];
        }

        return $dates;
    }

    /**
     * {@inheritdoc}
     */
    public function getTodaySummary(): array
    {
        $bindings = [];
        $userScope = UserScopedQuery::forTablePrepared('activity_log', $bindings);

        $tableName = Globals::table('activity_log');
        $row = Connection::preparedFetchOne(
            "SELECT terms_created, terms_reviewed, texts_read
             FROM {$tableName}
             WHERE date = CURDATE(){$userScope}",
            $bindings
        );

        if ($row === null) {
            return ['terms_created' => 0, 'terms_reviewed' => 0, 'texts_read' => 0];
        }

        return [
            'terms_created' => (int) $row['terms_created'],
            'terms_reviewed' => (int) $row['terms_reviewed'],
            'texts_read' => (int) $row['texts_read'],
        ];
    }

    /**
     * Atomically increment a column for today's row.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for upsert.
     *
     * @param string $column Column name to increment
     * @param int    $count  Amount to add
     *
     * @return void
     */
    private function incrementColumn(string $column, int $count): void
    {
        $tableName = Globals::table('activity_log');
        $userId = UserScopedQuery::getUserIdForInsert('activity_log');

        $bindings = [$userId, $count, $count];
        Connection::preparedExecute(
            "INSERT INTO {$tableName}
                (user_id, date, {$column})
             VALUES (?, CURDATE(), ?)
             ON DUPLICATE KEY UPDATE {$column} = {$column} + ?",
            $bindings
        );
    }
}
