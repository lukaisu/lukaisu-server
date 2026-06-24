<?php

/**
 * MySQL Article Repository
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Feed\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Feed\Infrastructure;

use Lukaisu\Shared\Infrastructure\Repository\AbstractRepository;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Feed\Domain\Article;
use Lukaisu\Modules\Feed\Domain\ArticleRepositoryInterface;

/**
 * MySQL repository for Article entities (feed_links).
 *
 * Provides database access for article management operations.
 *
 * @extends AbstractRepository<Article>
 *
 * @since 3.0.0
 */
class MySqlArticleRepository extends AbstractRepository implements ArticleRepositoryInterface
{
    /**
     * @var string Table name without prefix
     */
    protected string $tableName = 'feed_links';

    /**
     * @var string Primary key column
     */
    protected string $primaryKey = 'id';

    /**
     * @var array<string, string> Property to column mapping
     */
    protected array $columnMap = [
        'id' => 'id',
        'feedId' => 'feed_id',
        'title' => 'title',
        'link' => 'link',
        'description' => 'description',
        'date' => 'published_at',
        'audio' => 'audio',
        'text' => 'text',
    ];

    /**
     * {@inheritdoc}
     */
    protected function mapToEntity(array $row): Article
    {
        return Article::reconstitute(
            (int) $row['id'],
            (int) $row['feed_id'],
            (string) $row['title'],
            (string) $row['link'],
            (string) ($row['description'] ?? ''),
            (string) ($row['published_at'] ?? ''),
            (string) ($row['audio'] ?? ''),
            (string) ($row['text'] ?? '')
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param Article $entity
     *
     * @return array<string, scalar|null>
     */
    protected function mapToRow(object $entity): array
    {
        return [
            'feed_id' => $entity->feedId(),
            'title' => $entity->title(),
            'link' => $entity->link(),
            'description' => $entity->description(),
            'published_at' => $entity->date(),
            'audio' => $entity->audio(),
            'text' => $entity->text(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param Article $entity
     */
    protected function getEntityId(object $entity): int
    {
        return $entity->id() ?? 0;
    }

    /**
     * {@inheritdoc}
     *
     * @param Article $entity
     */
    protected function setEntityId(object $entity, int $id): void
    {
        $entity->setId($id);
    }

    /**
     * Append a `feed_id IN (SELECT id FROM news_feeds WHERE user_id = ?)`
     * fragment to $bindings when multi-user mode is on with an
     * authenticated user.
     *
     * `feed_links` doesn't carry its own `UsID` column, so QueryBuilder's
     * auto-scoping doesn't fire. Every code path that takes a
     * caller-supplied id/feed_id must run through this scope or
     * accept the full multi-tenant blast radius (read-foreign-articles,
     * delete-foreign-articles, etc.). The fragment is empty in
     * single-user installs so legacy tests stay green.
     *
     * @param array<int, mixed> &$bindings Reference to the bindings array
     *
     * @return string SQL fragment ready to splice after WHERE / AND, or ''
     */
    private function feedOwnerScope(array &$bindings): string
    {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }
        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }
        $bindings[] = $userId;
        return ' AND feed_id IN (SELECT id FROM news_feeds WHERE user_id = ?)';
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Article
    {
        /** @var array<int, mixed> $bindings */
        $bindings = [$id];
        $scope = $this->feedOwnerScope($bindings);
        $row = Connection::preparedFetchOne(
            'SELECT * FROM feed_links WHERE id = ?' . $scope . ' LIMIT 1',
            $bindings
        );
        if ($row === null) {
            return null;
        }
        return $this->mapToEntity($row);
    }

    /**
     * {@inheritdoc}
     */
    public function findByFeed(
        int $feedId,
        int $offset = 0,
        int $limit = 50,
        string $orderBy = 'published_at',
        string $direction = 'DESC'
    ): array {
        $rows = $this->query()
            ->where('feed_id', '=', $feedId)
            ->orderBy($orderBy, $direction)
            ->limit($limit)
            ->offset($offset)
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        /** @var array<int, mixed> $bindings */
        $bindings = [];
        $inClause = Connection::buildPreparedInClause(array_map('intval', $ids), $bindings);
        $scope = $this->feedOwnerScope($bindings);
        $rows = Connection::preparedFetchAll(
            'SELECT * FROM feed_links WHERE id IN ' . $inClause . $scope,
            $bindings
        );
        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    private const ALLOWED_ORDER_COLUMNS = [
        'published_at', 'title', 'id', 'link',
    ];

    private const ALLOWED_DIRECTIONS = ['ASC', 'DESC'];

    public function findByFeedsWithStatus(
        array $feedIds,
        int $offset = 0,
        int $limit = 50,
        string $orderBy = 'published_at',
        string $direction = 'DESC',
        string $search = ''
    ): array {
        if (empty($feedIds)) {
            return [];
        }

        if (!in_array($orderBy, self::ALLOWED_ORDER_COLUMNS, true)) {
            $orderBy = 'published_at';
        }
        $direction = strtoupper($direction);
        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            $direction = 'DESC';
        }

        $bindings = [];
        $feedInClause = Connection::buildPreparedInClause($feedIds, $bindings);

        // UserScopedQuery for texts LEFT JOIN (bindings added before WHERE)
        // We build this first since it goes in the JOIN clause
        $textScopeBindings = [];
        $textScope = UserScopedQuery::forTablePrepared('texts', $textScopeBindings, 'texts');

        // Build WHERE clause for search
        $searchClause = '';
        $searchBindings = [];
        if ($search !== '') {
            $searchClause = " AND (title LIKE ? OR description LIKE ?)";
            $searchBindings[] = '%' . $search . '%';
            $searchBindings[] = '%' . $search . '%';
        }

        // Merge all bindings in correct order: text scope, feed IDs, search
        $allBindings = array_merge($textScopeBindings, $bindings, $searchBindings);

        // Complex query with LEFT JOIN to texts (archived texts are in same table with TxArchivedAt)
        $sql = "SELECT id, feed_id, title, link, description, published_at, audio, text,
                       TxID, TxArchivedAt
                FROM feed_links
                LEFT JOIN texts ON TxSourceURI = TRIM(link)"
                . $textScope
                . " WHERE feed_id IN {$feedInClause} {$searchClause}"
                . " ORDER BY {$orderBy} {$direction}"
                . " LIMIT {$offset}, {$limit}";

        $result = [];
        $rows = Connection::preparedFetchAll($sql, $allBindings);

        foreach ($rows as $row) {
            $article = $this->mapToEntity($row);
            $textId = isset($row['TxID']) ? (int) $row['TxID'] : null;
            // Archived texts are identified by TxArchivedAt being non-null
            $isArchived = $textId !== null && isset($row['TxArchivedAt']) && $row['TxArchivedAt'] !== null;
            $archivedId = $isArchived ? $textId : null;
            $activeTextId = ($textId !== null && !$isArchived) ? $textId : null;

            $result[] = [
                'article' => $article,
                'text_id' => $activeTextId,
                'archived_id' => $archivedId,
                'status' => $article->determineStatus($activeTextId, $archivedId),
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function countByFeed(int $feedId, string $search = ''): int
    {
        if ($search !== '') {
            // Use raw SQL for OR condition
            $searchPattern = '%' . $search . '%';
            $bindings = [$feedId, $searchPattern, $searchPattern];
            $sql = "SELECT COUNT(*) as cnt FROM feed_links
                    WHERE feed_id = ?
                    AND (title LIKE ? OR description LIKE ?)";
            $row = Connection::preparedFetchOne($sql, $bindings);
            return (int) ($row['cnt'] ?? 0);
        }

        return $this->query()
            ->where('feed_id', '=', $feedId)
            ->countPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function countByFeeds(array $feedIds, string $search = ''): int
    {
        if (empty($feedIds)) {
            return 0;
        }

        if ($search !== '') {
            // Use raw SQL for OR condition with IN clause
            $bindings = [];
            $feedInClause = Connection::buildPreparedInClause($feedIds, $bindings);
            $searchPattern = '%' . $search . '%';
            $bindings[] = $searchPattern;
            $bindings[] = $searchPattern;
            $sql = "SELECT COUNT(*) as cnt FROM feed_links
                    WHERE feed_id IN {$feedInClause}
                    AND (title LIKE ? OR description LIKE ?)";
            $row = Connection::preparedFetchOne($sql, $bindings);
            return (int) ($row['cnt'] ?? 0);
        }

        return $this->query()
            ->whereIn('feed_id', $feedIds)
            ->countPrepared();
    }

    /**
     * {@inheritdoc}
     *
     * @param Article $entity
     */
    public function save(object $entity): int
    {
        $data = $this->mapToRow($entity);

        if ($entity->isNew()) {
            // Insert
            $id = (int) $this->query()->insertPrepared($data);
            $entity->setId($id);
            return $id;
        }

        // Update - entity must have an ID at this point
        $id = (int) $entity->id();
        $this->query()
            ->where($this->primaryKey, '=', $id)
            ->updatePrepared($data);

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function insertBatch(array $articles, int $feedId): array
    {
        $inserted = 0;
        $duplicates = 0;

        foreach ($articles as $article) {
            // Check for duplicate by title (unique key: feed_id, title)
            if ($this->titleExistsForFeed($feedId, $article->title())) {
                $duplicates++;
                continue;
            }

            try {
                $this->save($article);
                $inserted++;
            } catch (\mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    // Duplicate key error
                    $duplicates++;
                } else {
                    error_log(
                        'MySqlArticleRepository::insertBatch: '
                        . $e->getMessage()
                    );
                    $duplicates++;
                }
            } catch (\Exception $e) {
                error_log(
                    'MySqlArticleRepository::insertBatch: '
                    . $e->getMessage()
                );
                $duplicates++;
            }
        }

        return [
            'inserted' => $inserted,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function delete(object|int $entityOrId): bool
    {
        /** @var Article|int $entityOrId */
        $id = is_int($entityOrId) ? $entityOrId : $this->getEntityId($entityOrId);
        /** @var array<int, mixed> $bindings */
        $bindings = [$id];
        $scope = $this->feedOwnerScope($bindings);
        $deleted = Connection::preparedExecute(
            'DELETE FROM feed_links WHERE id = ?' . $scope,
            $bindings
        );
        return $deleted > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByFeed(int $feedId): int
    {
        /** @var array<int, mixed> $bindings */
        $bindings = [$feedId];
        $scope = $this->feedOwnerScope($bindings);
        return Connection::preparedExecute(
            'DELETE FROM feed_links WHERE feed_id = ?' . $scope,
            $bindings
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByFeeds(array $feedIds): int
    {
        if (empty($feedIds)) {
            return 0;
        }
        /** @var array<int, mixed> $bindings */
        $bindings = [];
        $inClause = Connection::buildPreparedInClause(array_map('intval', $feedIds), $bindings);
        $scope = $this->feedOwnerScope($bindings);
        return Connection::preparedExecute(
            'DELETE FROM feed_links WHERE feed_id IN ' . $inClause . $scope,
            $bindings
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        /** @var array<int, mixed> $bindings */
        $bindings = [];
        $inClause = Connection::buildPreparedInClause(array_map('intval', $ids), $bindings);
        $scope = $this->feedOwnerScope($bindings);
        return Connection::preparedExecute(
            'DELETE FROM feed_links WHERE id IN ' . $inClause . $scope,
            $bindings
        );
    }

    /**
     * {@inheritdoc}
     */
    public function resetErrorsByFeeds(array $feedIds): int
    {
        if (empty($feedIds)) {
            return 0;
        }

        $bindings = [];
        $feedInClause = Connection::buildPreparedInClause($feedIds, $bindings);

        // Use raw SQL for TRIM() expression
        return Connection::preparedExecute(
            "UPDATE feed_links SET link = TRIM(link)
             WHERE feed_id IN {$feedInClause}",
            $bindings
        );
    }

    /**
     * {@inheritdoc}
     */
    public function markAsError(string $link): void
    {
        // Add space prefix to mark as error
        $this->query()
            ->where('link', '=', $link)
            ->updatePrepared(['link' => ' ' . $link]);
    }

    /**
     * {@inheritdoc}
     */
    public function titleExistsForFeed(int $feedId, string $title): bool
    {
        return $this->query()
            ->where('feed_id', '=', $feedId)
            ->where('title', '=', $title)
            ->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function getCountPerFeed(array $feedIds = []): array
    {
        /** @var array<int, mixed> $bindings */
        $bindings = [];
        $sql = "SELECT feed_id, COUNT(*) as cnt FROM feed_links";

        if (!empty($feedIds)) {
            $feedInClause = Connection::buildPreparedInClause($feedIds, $bindings);
            $scope = $this->feedOwnerScope($bindings);
            $sql .= " WHERE feed_id IN {$feedInClause}" . $scope;
        } else {
            // Empty feedIds means "give me counts for every feed". In
            // multi-user mode, restrict that to the caller's feeds.
            $scope = $this->feedOwnerScope($bindings);
            if ($scope !== '') {
                // feedOwnerScope yields ` AND feed_id IN (...)`; drop the
                // leading ` AND ` (5 chars) so it can start a fresh WHERE.
                $sql .= ' WHERE ' . substr($scope, 5);
            }
        }

        $sql .= " GROUP BY feed_id";

        $rows = Connection::preparedFetchAll($sql, $bindings);
        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['feed_id']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
