<?php

/**
 * Term Query Methods Trait
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Vocabulary\Domain\Term;
use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermStatus;

/**
 * Query methods for MySqlTermRepository.
 *
 * Provides find/search/pagination methods that read term data
 * from the database without modifying it.
 */
trait TermQueryMethods
{
    /**
     * Get a query builder for this repository's table.
     *
     * @return QueryBuilder
     */
    abstract protected function query(): QueryBuilder;

    /**
     * Map a database row to a Term entity.
     *
     * @param array<string, mixed> $row Database row
     *
     * @return Term
     */
    abstract protected function mapToEntity(array $row): Term;

    /**
     * {@inheritdoc}
     */
    public function findByStatus(int $status, ?int $languageId = null): array
    {
        $query = $this->query()
            ->where('status', '=', $status)
            ->orderBy('text');

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findLearning(?int $languageId = null): array
    {
        $query = $this->query()
            ->whereIn('status', [
                TermStatus::NEW,
                TermStatus::LEARNING_2,
                TermStatus::LEARNING_3,
                TermStatus::LEARNING_4
            ])
            ->orderBy('text');

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findKnown(?int $languageId = null): array
    {
        $query = $this->query()
            ->whereIn('status', [TermStatus::LEARNED, TermStatus::WELL_KNOWN])
            ->orderBy('text');

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findIgnored(?int $languageId = null): array
    {
        return $this->findByStatus(TermStatus::IGNORED, $languageId);
    }

    /**
     * {@inheritdoc}
     */
    public function findMultiWord(?int $languageId = null): array
    {
        $query = $this->query()
            ->where('word_count', '>', 1)
            ->orderBy('text');

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find single-word terms (word count = 1).
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return Term[]
     */
    public function findSingleWord(?int $languageId = null): array
    {
        $query = $this->query()
            ->where('word_count', '=', 1)
            ->orderBy('text');

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find all terms sharing a lemma in a language (word family).
     *
     * @param int    $languageId Language ID
     * @param string $lemmaLc    Lowercase lemma
     *
     * @return Term[]
     */
    public function findByLemma(int $languageId, string $lemmaLc): array
    {
        $rows = $this->query()
            ->where('language_id', '=', $languageId)
            ->where('lemma_lc', '=', $lemmaLc)
            ->orderBy('word_count')
            ->orderBy('text')
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findPaginated(
        int $languageId = 0,
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'text',
        string $direction = 'ASC'
    ): array {
        $query = $this->query();

        if ($languageId > 0) {
            $query->where('language_id', '=', $languageId);
        }

        $total = (clone $query)->countPrepared();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        // Ensure page is within bounds
        $page = max(1, min($page, max(1, $totalPages)));
        $offset = ($page - 1) * $perPage;

        $rows = $query
            ->orderBy($orderBy, $direction)
            ->limit($perPage)
            ->offset($offset)
            ->getPrepared();

        $items = array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function searchByText(string $query, ?int $languageId = null, int $limit = 50): array
    {
        $searchPattern = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

        $dbQuery = $this->query()
            ->where('text', 'LIKE', $searchPattern)
            ->orderBy('text')
            ->limit($limit);

        if ($languageId !== null) {
            $dbQuery->where('language_id', '=', $languageId);
        }

        $rows = $dbQuery->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Search terms by translation.
     *
     * @param string   $query      Search query
     * @param int|null $languageId Language ID (null for all)
     * @param int      $limit      Maximum results
     *
     * @return Term[]
     */
    public function searchByTranslation(string $query, ?int $languageId = null, int $limit = 50): array
    {
        $searchPattern = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

        $dbQuery = $this->query()
            ->where('translation', 'LIKE', $searchPattern)
            ->orderBy('text')
            ->limit($limit);

        if ($languageId !== null) {
            $dbQuery->where('language_id', '=', $languageId);
        }

        $rows = $dbQuery->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findForReview(
        ?int $languageId = null,
        float $scoreThreshold = 0.0,
        int $limit = 100
    ): array {
        // FSRS (issue #238): a term is due when its due_at has passed. The
        // legacy $scoreThreshold is retained for interface compatibility but no
        // longer used — due-ness is the absolute due_at, not a decaying score.
        unset($scoreThreshold);
        $query = $this->query()
            ->whereIn('status', [
                TermStatus::NEW,
                TermStatus::LEARNING_2,
                TermStatus::LEARNING_3,
                TermStatus::LEARNING_4,
                TermStatus::LEARNED
            ])
            ->whereRaw('due_at <= NOW()')
            ->orderBy('due_at', 'ASC')
            ->limit($limit);

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findRecent(?int $languageId = null, int $limit = 50): array
    {
        $query = $this->query()
            ->orderBy('created_at', 'DESC')
            ->limit($limit);

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find terms with status changed recently.
     *
     * @param int|null $languageId Language ID (null for all)
     * @param int      $days       Number of days to look back
     * @param int      $limit      Maximum results
     *
     * @return Term[]
     */
    public function findRecentlyChanged(
        ?int $languageId = null,
        int $days = 7,
        int $limit = 50
    ): array {
        $timestamp = strtotime("-{$days} days");
        $sinceDate = date('Y-m-d H:i:s', $timestamp !== false ? $timestamp : time());

        $query = $this->query()
            ->where('status_changed_at', '>=', $sinceDate)
            ->orderBy('status_changed_at', 'DESC')
            ->limit($limit);

        if ($languageId !== null) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find terms without translation.
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return Term[]
     */
    public function findWithoutTranslation(?int $languageId = null): array
    {
        // Use raw SQL for OR condition on translation (empty or '*')
        $rows = Connection::preparedFetchAll(
            "SELECT * FROM words WHERE (translation = '' OR translation = '*')"
            . ($languageId !== null ? " AND language_id = ?" : "")
            . " ORDER BY text",
            $languageId !== null ? [$languageId] : []
        );

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Get terms formatted for select dropdown options.
     *
     * @param int $languageId    Language ID (0 for all languages)
     * @param int $maxNameLength Maximum text length before truncation
     *
     * @return array<int, array{id: int, text: string, language_id: int}>
     */
    public function getForSelect(int $languageId = 0, int $maxNameLength = 40): array
    {
        $query = $this->query()
            ->select(['id', 'text', 'language_id'])
            ->orderBy('text');

        if ($languageId > 0) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();
        $result = [];

        foreach ($rows as $row) {
            $text = (string) $row['text'];
            if (mb_strlen($text, 'UTF-8') > $maxNameLength) {
                $text = mb_substr($text, 0, $maxNameLength, 'UTF-8') . '...';
            }
            $result[] = [
                'id' => (int) $row['id'],
                'text' => $text,
                'language_id' => (int) $row['language_id'],
            ];
        }

        return $result;
    }

    /**
     * Get basic term info (minimal data for lists).
     *
     * @param int $termId Term ID
     *
     * @return array{id: int, text: string, language_id: int, status: int, has_translation: bool}|null
     */
    public function getBasicInfo(int $termId): ?array
    {
        $row = $this->query()
            ->select([
                'id',
                'text',
                'language_id',
                'status',
                'translation',
            ])
            ->where('id', '=', $termId)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        $translation = (string) ($row['translation'] ?? '');

        return [
            'id' => (int) $row['id'],
            'text' => (string) $row['text'],
            'language_id' => (int) $row['language_id'],
            'status' => (int) $row['status'],
            'has_translation' => $translation !== '' && $translation !== '*',
        ];
    }
}
