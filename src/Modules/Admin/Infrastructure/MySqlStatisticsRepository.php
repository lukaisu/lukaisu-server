<?php

/**
 * MySQL Statistics Repository
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;

/**
 * MySQL repository for statistics queries.
 *
 * Provides database access for learning statistics.
 *
 * @since 3.0.0
 */
class MySqlStatisticsRepository
{
    /**
     * Get term counts grouped by language and status.
     *
     * @return array<string, array<int, int>> Term counts indexed by language ID and status
     */
    public function getTermCountsByLanguageAndStatus(): array
    {
        $results = QueryBuilder::table('words')
            ->selectRaw('language_id, status, COUNT(*) AS term_count')
            ->groupBy(['language_id', 'status'])
            ->getPrepared();

        $termStat = [];
        foreach ($results as $record) {
            $lgId = (string) $record['language_id'];
            $status = (int) $record['status'];
            $termStat[$lgId][$status] = (int) $record['term_count'];
        }

        return $termStat;
    }

    /**
     * Get list of languages with IDs and names.
     *
     * Returns records with id (int) and name (string) fields.
     *
     * @return array<int, array<string, mixed>> Language records
     */
    public function getLanguageList(): array
    {
        return QueryBuilder::table('languages')
            ->select(['id', 'name'])
            ->where('name', '<>', '')
            ->orderBy('name')
            ->getPrepared();
    }

    /**
     * Get terms created grouped by language and days ago.
     *
     * @return array<int, array<int, int>> Terms by language ID and days since creation
     */
    public function getTermsCreatedByDay(): array
    {
        $results = QueryBuilder::table('words')
            ->select([
                'language_id',
                'TO_DAYS(curdate()) - TO_DAYS(cast(created_at as date)) AS Created',
                'count(id) as value'
            ])
            ->whereIn('status', [1, 2, 3, 4, 5, 99])
            ->groupBy(['language_id', 'Created'])
            ->getPrepared();

        /** @var array<int, array<int, int>> $termCreated */
        $termCreated = [];
        foreach ($results as $record) {
            $lgId = (int) ($record['language_id'] ?? 0);
            $created = (int) ($record['Created'] ?? 0);
            $termCreated[$lgId][$created] = (int) ($record['value'] ?? 0);
        }

        return $termCreated;
    }

    /**
     * Get term activity grouped by language and days ago.
     *
     * @return array{active: array<int, array<int, int>>, known: array<int, array<int, int>>}
     */
    public function getTermActivityByDay(): array
    {
        $results = QueryBuilder::table('words')
            ->select([
                'language_id',
                'status',
                'TO_DAYS(curdate()) - TO_DAYS(cast(status_changed_at as date)) AS Changed',
                'count(id) as value'
            ])
            ->groupBy(['language_id', 'status', 'status_changed_at'])
            ->getPrepared();

        /** @var array<int, array<int, int>> $termActive */
        $termActive = [];
        /** @var array<int, array<int, int>> $termKnown */
        $termKnown = [];

        foreach ($results as $record) {
            $status = (int) ($record['status'] ?? 0);
            if ($status > 0) {
                $lgId = (int) ($record['language_id'] ?? 0);
                $changed = (int) ($record['Changed'] ?? 0);
                $value = (int) ($record['value'] ?? 0);

                if ($status == 5 || $status == 99) {
                    if (!isset($termKnown[$lgId][$changed])) {
                        $termKnown[$lgId][$changed] = 0;
                    }
                    $termKnown[$lgId][$changed] += $value;

                    if (!isset($termActive[$lgId][$changed])) {
                        $termActive[$lgId][$changed] = 0;
                    }
                    $termActive[$lgId][$changed] += $value;
                } elseif ($status > 0 && $status < 5) {
                    if (!isset($termActive[$lgId][$changed])) {
                        $termActive[$lgId][$changed] = 0;
                    }
                    $termActive[$lgId][$changed] += $value;
                }
            }
        }

        return [
            'active' => $termActive,
            'known' => $termKnown
        ];
    }

    /**
     * Get language count.
     *
     * @return int Number of languages
     */
    public function getLanguageCount(): int
    {
        return QueryBuilder::table('languages')->count();
    }
}
