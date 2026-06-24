<?php

/**
 * MySQL Text Tag Repository
 *
 * Infrastructure adapter for text tag persistence using MySQL.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Tags\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Tags\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\Maintenance;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Tags\Domain\Tag;
use Lukaisu\Modules\Tags\Domain\TagRepositoryInterface;
use Lukaisu\Modules\Tags\Domain\TagType;
use Lukaisu\Modules\Tags\Domain\ValueObject\TagId;

/**
 * MySQL implementation of TagRepositoryInterface for text tags.
 *
 * Operates on the 'text_tags' table for text/document tags.
 *
 * @since 3.0.0
 */
class MySqlTextTagRepository implements TagRepositoryInterface
{
    private const TABLE_NAME = 'text_tags';
    private const COL_PREFIX = 'T2';

    /**
     * {@inheritdoc}
     */
    public function getTagType(): TagType
    {
        return TagType::TEXT;
    }

    /**
     * Get a query builder for this repository's table.
     *
     * @return QueryBuilder
     */
    private function query(): QueryBuilder
    {
        return QueryBuilder::table(self::TABLE_NAME);
    }

    /**
     * Map a database row to a Tag entity.
     *
     * @param array<string, mixed> $row Database row
     *
     * @return Tag
     */
    private function mapToEntity(array $row): Tag
    {
        return Tag::reconstitute(
            (int) $row['id'],
            TagType::TEXT,
            (string) $row['text'],
            (string) ($row['comment'] ?? '')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Tag
    {
        $row = $this->query()
            ->where('id', '=', $id)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    /**
     * {@inheritdoc}
     */
    public function findByText(string $text): ?Tag
    {
        $row = $this->query()
            ->where('text', '=', $text)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    /**
     * {@inheritdoc}
     */
    public function save(Tag $tag): void
    {
        $id = $tag->id()->toInt();

        if ($id > 0 && !$tag->id()->isNew()) {
            // Update existing
            $this->query()
                ->where('id', '=', $id)
                ->updatePrepared([
                    'text' => $tag->text(),
                    'comment' => $tag->comment(),
                ]);
            return;
        }

        // Insert new
        $newId = (int) $this->query()->insertPrepared([
            'text' => $tag->text(),
            'comment' => $tag->comment(),
        ]);
        $tag->setId(TagId::fromInt($newId));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $affected = $this->query()
            ->where('id', '=', $id)
            ->deletePrepared();

        if ($affected > 0) {
            Maintenance::adjustAutoIncrement(self::TABLE_NAME, 'id');
        }

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $affected = $this->query()
            ->whereIn('id', $ids)
            ->deletePrepared();

        if ($affected > 0) {
            Maintenance::adjustAutoIncrement(self::TABLE_NAME, 'id');
        }

        return $affected;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(string $query = ''): int
    {
        if ($query === '') {
            $affected = $this->query()->deletePrepared();
        } else {
            $whereData = $this->buildWhereClause($query);
            /** @var array<int, mixed> $bindings */
            $bindings = $whereData['params'];
            $userScope = UserScopedQuery::forTablePrepared(self::TABLE_NAME, $bindings);
            $affected = Connection::preparedExecute(
                'DELETE FROM ' . self::TABLE_NAME . ' WHERE (1=1) '
                    . $whereData['clause'] . $userScope,
                $bindings
            );
        }

        if ($affected > 0) {
            Maintenance::adjustAutoIncrement(self::TABLE_NAME, 'id');
        }

        return $affected;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(int $id): bool
    {
        return $this->query()
            ->where('id', '=', $id)
            ->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function textExists(string $text, ?int $excludeId = null): bool
    {
        $query = $this->query()->where('text', '=', $text);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(string $orderBy = 'text', string $direction = 'ASC'): array
    {
        $column = $this->mapOrderByColumn($orderBy);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $rows = $this->query()
            ->orderBy($column, $direction)
            ->getPrepared();

        return array_map(fn($row) => $this->mapToEntity($row), $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(
        int $page,
        int $perPage,
        string $query = '',
        string $orderBy = 'text'
    ): array {
        $whereData = $this->buildWhereClause($query);
        $column = $this->mapOrderByColumn($orderBy);
        $offset = ($page - 1) * $perPage;

        // Get total count
        $totalCount = $this->count($query);

        // Get paginated rows. The base WHERE (1=1) anchor lets us safely
        // append both the optional search clause and the user-scope clause
        // without worrying about whether either is empty.
        /** @var array<int, mixed> $bindings */
        $bindings = $whereData['params'];
        $userScope = UserScopedQuery::forTablePrepared(self::TABLE_NAME, $bindings);
        $sql = 'SELECT id, text, comment FROM ' . self::TABLE_NAME .
               ' WHERE (1=1) ' . $whereData['clause'] . $userScope .
               ' ORDER BY ' . $column .
               ' LIMIT ' . $offset . ',' . $perPage;

        $rows = Connection::preparedFetchAll($sql, $bindings);

        $tags = [];
        $usageCounts = [];

        foreach ($rows as $row) {
            $tag = $this->mapToEntity($row);
            $tags[] = $tag;
            $tagId = $tag->id()->toInt();
            $usageCounts[$tagId] = $this->getUsageCount($tagId);
        }

        return [
            'tags' => $tags,
            'usageCounts' => $usageCounts,
            'totalCount' => $totalCount,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $query = ''): int
    {
        if ($query === '') {
            return $this->query()->count('id');
        }

        $whereData = $this->buildWhereClause($query);
        /** @var array<int, mixed> $bindings */
        $bindings = $whereData['params'];
        $userScope = UserScopedQuery::forTablePrepared(self::TABLE_NAME, $bindings);
        return (int) Connection::preparedFetchValue(
            'SELECT COUNT(id) AS cnt FROM ' . self::TABLE_NAME .
            ' WHERE (1=1) ' . $whereData['clause'] . $userScope,
            $bindings,
            'cnt'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAllTexts(): array
    {
        $rows = $this->query()
            ->select(['text'])
            ->orderBy('text', 'ASC')
            ->getPrepared();

        /** @var list<string> */
        return array_column($rows, 'text');
    }

    /**
     * {@inheritdoc}
     */
    public function getUsageCount(int $tagId): int
    {
        return QueryBuilder::table('text_tag_map')
            ->where('text_tag_id', '=', $tagId)
            ->count();
    }

    /**
     * Get the number of archived texts using this tag.
     *
     * @param int $tagId Tag ID
     *
     * @return int Archived usage count
     */
    public function getArchivedUsageCount(int $tagId): int
    {
        return QueryBuilder::table('archived_text_tag_map')
            ->where('AgT2ID', '=', $tagId)
            ->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getOrCreate(string $text): int
    {
        $text = trim(str_replace([' ', ','], '', $text));

        if ($text === '') {
            throw new \InvalidArgumentException('Tag text cannot be empty');
        }

        $existing = $this->findByText($text);
        if ($existing !== null) {
            return $existing->id()->toInt();
        }

        $tag = Tag::create(TagType::TEXT, $text);
        $this->save($tag);

        return $tag->id()->toInt();
    }

    /**
     * Build WHERE clause for query filtering.
     *
     * @param string $query Filter query string
     *
     * @return array{clause: string, params: list<string>}
     */
    private function buildWhereClause(string $query): array
    {
        if ($query === '') {
            return ['clause' => '', 'params' => []];
        }

        $searchValue = str_replace('*', '%', $query);
        $clause = ' AND (text LIKE ? OR comment LIKE ?)';

        return ['clause' => $clause, 'params' => [$searchValue, $searchValue]];
    }

    /**
     * Map order by parameter to column name.
     *
     * @param string $orderBy Order by parameter
     *
     * @return string Column name with direction
     */
    private function mapOrderByColumn(string $orderBy): string
    {
        return match (strtolower($orderBy)) {
            'text' => 'text ASC',
            'comment' => 'comment ASC',
            'newest', 'id desc' => 'id DESC',
            'oldest', 'id asc' => 'id ASC',
            default => 'text ASC',
        };
    }
}
