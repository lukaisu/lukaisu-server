<?php

/**
 * Activity Repository Interface
 *
 * PHP version 8.2
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Activity\Domain
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Activity\Domain;

/**
 * Repository interface for daily activity tracking.
 */
interface ActivityRepositoryInterface
{
    /**
     * Increment the terms-created counter for today.
     *
     * @param int $count Number to add (default 1)
     *
     * @return void
     */
    public function incrementTermsCreated(int $count = 1): void;

    /**
     * Increment the terms-reviewed counter for today.
     *
     * @param int $count Number to add (default 1)
     *
     * @return void
     */
    public function incrementTermsReviewed(int $count = 1): void;

    /**
     * Increment the texts-read counter for today.
     *
     * @param int $count Number to add (default 1)
     *
     * @return void
     */
    public function incrementTextsRead(int $count = 1): void;

    /**
     * Get activity data for a date range.
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate   End date (Y-m-d)
     *
     * @return array<int, array{date: string, terms_created: int, terms_reviewed: int, texts_read: int}>
     */
    public function getActivityForDateRange(string $startDate, string $endDate): array;

    /**
     * Get all dates with any activity, ordered most recent first.
     *
     * @return list<string> Dates in Y-m-d format
     */
    public function getActiveDatesDescending(): array;

    /**
     * Get today's activity summary.
     *
     * @return array{terms_created: int, terms_reviewed: int, texts_read: int}
     */
    public function getTodaySummary(): array;
}
