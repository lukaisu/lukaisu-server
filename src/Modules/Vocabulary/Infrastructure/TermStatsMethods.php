<?php

/**
 * Term Stats Methods Trait
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermStatus;

/**
 * Statistics, update, and bulk operation methods for MySqlTermRepository.
 *
 * Provides methods for updating individual term fields, bulk operations
 * on multiple terms, and statistical/distribution queries.
 *
 * @since 3.0.0
 */
trait TermStatsMethods
{
    /**
     * Get a query builder for this repository's table.
     *
     * @return QueryBuilder
     */
    abstract protected function query(): QueryBuilder;

    /**
     * {@inheritdoc}
     */
    public function updateStatus(int $termId, int $status): bool
    {
        $affected = $this->query()
            ->where('id', '=', $termId)
            ->updatePrepared([
                'status' => $status,
                'status_changed_at' => date('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTranslation(int $termId, string $translation): bool
    {
        $affected = $this->query()
            ->where('id', '=', $termId)
            ->updatePrepared(['translation' => $translation]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRomanization(int $termId, string $romanization): bool
    {
        $affected = $this->query()
            ->where('id', '=', $termId)
            ->updatePrepared(['romanization' => $romanization]);

        return $affected > 0;
    }

    /**
     * Update the example sentence of a term.
     *
     * @param int    $termId   Term ID
     * @param string $sentence New sentence
     *
     * @return bool True if updated
     */
    public function updateSentence(int $termId, string $sentence): bool
    {
        $affected = $this->query()
            ->where('id', '=', $termId)
            ->updatePrepared(['sentence' => $sentence]);

        return $affected > 0;
    }

    /**
     * Update the notes of a term.
     *
     * @param int    $termId Term ID
     * @param string $notes  New notes
     *
     * @return bool True if updated
     */
    public function updateNotes(int $termId, string $notes): bool
    {
        $affected = $this->query()
            ->where('id', '=', $termId)
            ->updatePrepared(['notes' => $notes]);

        return $affected > 0;
    }

    /**
     * Update the lemma (base form) of a term.
     *
     * @param int         $termId Term ID
     * @param string|null $lemma  New lemma (null to clear)
     *
     * @return bool True if updated
     */
    public function updateLemma(int $termId, ?string $lemma): bool
    {
        $lemmaLc = $lemma !== null && $lemma !== '' ? mb_strtolower($lemma, 'UTF-8') : null;

        $affected = $this->query()
            ->where('id', '=', $termId)
            ->updatePrepared([
                'lemma' => $lemma,
                'lemma_lc' => $lemmaLc,
            ]);

        return $affected > 0;
    }

    /**
     * Update review scores for a term.
     *
     * @param int   $termId        Term ID
     * @param float $todayScore    Today's score
     * @param float $tomorrowScore Tomorrow's score
     *
     * @return bool True if updated
     */
    public function updateScores(int $termId, float $todayScore, float $tomorrowScore): bool
    {
        $affected = $this->query()
            ->where('id', '=', $termId)
            ->updatePrepared([
                'today_score' => $todayScore,
                'tomorrow_score' => $tomorrowScore,
            ]);

        return $affected > 0;
    }

    /**
     * Get language IDs that have terms.
     *
     * @return int[] Array of language IDs
     */
    public function getLanguagesWithTerms(): array
    {
        $rows = $this->query()
            ->select('DISTINCT language_id')
            ->getPrepared();

        return array_map(
            fn(array $row) => (int) $row['language_id'],
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getStatistics(?int $languageId = null): array
    {
        $baseQuery = $this->query();
        if ($languageId !== null) {
            $baseQuery->where('language_id', '=', $languageId);
        }

        $total = (clone $baseQuery)->countPrepared();

        $learning = (clone $baseQuery)
            ->whereIn('status', [
                TermStatus::NEW,
                TermStatus::LEARNING_2,
                TermStatus::LEARNING_3,
                TermStatus::LEARNING_4
            ])
            ->countPrepared();

        $known = (clone $baseQuery)
            ->whereIn('status', [TermStatus::LEARNED, TermStatus::WELL_KNOWN])
            ->countPrepared();

        $ignored = (clone $baseQuery)
            ->where('status', '=', TermStatus::IGNORED)
            ->countPrepared();

        $multiWord = (clone $baseQuery)
            ->where('word_count', '>', 1)
            ->countPrepared();

        return [
            'total' => $total,
            'learning' => $learning,
            'known' => $known,
            'ignored' => $ignored,
            'multi_word' => $multiWord,
        ];
    }

    /**
     * Get status distribution counts.
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return array<int, int> Status value => count
     */
    public function getStatusDistribution(?int $languageId = null): array
    {
        $baseQuery = $this->query();
        if ($languageId !== null) {
            $baseQuery->where('language_id', '=', $languageId);
        }

        $statuses = [
            TermStatus::NEW,
            TermStatus::LEARNING_2,
            TermStatus::LEARNING_3,
            TermStatus::LEARNING_4,
            TermStatus::LEARNED,
            TermStatus::IGNORED,
            TermStatus::WELL_KNOWN,
        ];

        $distribution = [];
        foreach ($statuses as $status) {
            $distribution[$status] = (clone $baseQuery)
                ->where('status', '=', $status)
                ->countPrepared();
        }

        return $distribution;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $termIds): int
    {
        if (empty($termIds)) {
            return 0;
        }

        return $this->query()
            ->whereIn('id', array_map('intval', $termIds))
            ->deletePrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatusMultiple(array $termIds, int $status): int
    {
        if (empty($termIds)) {
            return 0;
        }

        return $this->query()
            ->whereIn('id', array_map('intval', $termIds))
            ->updatePrepared([
                'status' => $status,
                'status_changed_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get term count by word count.
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return array<int, int> Word count => term count
     */
    public function getWordCountDistribution(?int $languageId = null): array
    {
        $sql = "SELECT word_count, COUNT(*) as cnt FROM words";
        $params = [];

        if ($languageId !== null) {
            $sql .= " WHERE language_id = ?";
            $params[] = $languageId;
        }

        $sql .= " GROUP BY word_count ORDER BY word_count";

        $rows = Connection::preparedFetchAll($sql, $params);

        $distribution = [];
        foreach ($rows as $row) {
            $distribution[(int) $row['word_count']] = (int) $row['cnt'];
        }

        return $distribution;
    }
}
