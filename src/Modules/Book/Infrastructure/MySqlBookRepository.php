<?php

/**
 * MySQL Book Repository
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Book\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Book\Infrastructure;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Book\Domain\Book;
use Lukaisu\Modules\Book\Domain\BookRepositoryInterface;

/**
 * MySQL repository for Book entities.
 *
 * Provides database access for book management operations.
 *
 * @since 3.0.0
 */
class MySqlBookRepository implements BookRepositoryInterface
{
    /**
     * @var string Table name without prefix
     */
    protected string $tableName = 'books';

    /**
     * @var string Primary key column
     */
    protected string $primaryKey = 'id';

    /**
     * @var array<string, string> Property to column mapping
     */
    protected array $columnMap = [
        'id' => 'id',
        'userId' => 'user_id',
        'languageId' => 'language_id',
        'title' => 'title',
        'author' => 'author',
        'description' => 'description',
        'coverPath' => 'cover_path',
        'sourceType' => 'source_type',
        'sourceHash' => 'source_hash',
        'totalChapters' => 'total_chapters',
        'currentChapter' => 'current_chapter',
    ];

    /**
     * Create a query builder for the books table.
     *
     * @return QueryBuilder
     */
    protected function query(): QueryBuilder
    {
        return Globals::query($this->tableName);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        $db = Globals::getDbConnection();
        if ($db !== null) {
            $db->begin_transaction();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $db = Globals::getDbConnection();
        if ($db !== null) {
            $db->commit();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        $db = Globals::getDbConnection();
        if ($db !== null) {
            $db->rollback();
        }
    }

    /**
     * Map a database row to a Book entity.
     *
     * @param array<string, mixed> $row Database row
     *
     * @return Book
     */
    protected function mapToEntity(array $row): Book
    {
        return Book::reconstitute(
            (int) $row['id'],
            isset($row['user_id']) ? (int) $row['user_id'] : null,
            (int) $row['language_id'],
            (string) $row['title'],
            isset($row['author']) ? (string) $row['author'] : null,
            isset($row['description']) ? (string) $row['description'] : null,
            isset($row['cover_path']) ? (string) $row['cover_path'] : null,
            (string) ($row['source_type'] ?? 'text'),
            isset($row['source_hash']) ? (string) $row['source_hash'] : null,
            (int) ($row['total_chapters'] ?? 0),
            (int) ($row['current_chapter'] ?? 1),
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param Book $entity
     *
     * @return array<string, scalar|null>
     */
    protected function mapToRow(object $entity): array
    {
        return [
            'user_id' => $entity->userId(),
            'language_id' => $entity->languageId(),
            'title' => $entity->title(),
            'author' => $entity->author(),
            'description' => $entity->description(),
            'cover_path' => $entity->coverPath(),
            'source_type' => $entity->sourceType(),
            'source_hash' => $entity->sourceHash(),
            'total_chapters' => $entity->totalChapters(),
            'current_chapter' => $entity->currentChapter(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param Book $entity
     */
    protected function getEntityId(object $entity): int
    {
        return $entity->id() ?? 0;
    }

    /**
     * {@inheritdoc}
     *
     * @param Book $entity
     */
    protected function setEntityId(object $entity, int $id): void
    {
        $entity->setId($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Book
    {
        $row = $this->query()
            ->where($this->primaryKey, '=', $id)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySourceHash(string $hash, ?int $userId = null): ?Book
    {
        $query = $this->query()
            ->where('source_hash', '=', $hash);

        if ($userId !== null) {
            $query->where('user_id', '=', $userId);
        }

        $row = $query->firstPrepared();

        if ($row === null) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    /**
     * {@inheritdoc}
     */
    public function existsBySourceHash(string $hash, ?int $userId = null): bool
    {
        $query = $this->query()
            ->where('source_hash', '=', $hash);

        if ($userId !== null) {
            $query->where('user_id', '=', $userId);
        }

        return $query->existsPrepared();
    }

    /**
     * {@inheritdoc}
     *
     * @return Book[]
     */
    public function findAll(
        ?int $userId = null,
        ?int $languageId = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = $this->query()
            ->orderBy('updated_at', 'DESC')
            ->limit($limit)
            ->offset($offset);

        if ($userId !== null) {
            $query->where('user_id', '=', $userId);
        }

        if ($languageId !== null && $languageId > 0) {
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
    public function count(?int $userId = null, ?int $languageId = null): int
    {
        $query = $this->query();

        if ($userId !== null) {
            $query->where('user_id', '=', $userId);
        }

        if ($languageId !== null && $languageId > 0) {
            $query->where('language_id', '=', $languageId);
        }

        return $query->countPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Book $book): int
    {
        $data = $this->mapToRow($book);

        if ($book->isNew()) {
            $id = (int) $this->query()->insertPrepared($data);
            $book->setId($id);
            return $id;
        }

        $id = (int) $book->id();
        $this->query()
            ->where($this->primaryKey, '=', $id)
            ->updatePrepared($data);

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $deleted = $this->query()
            ->where($this->primaryKey, '=', $id)
            ->deletePrepared();

        return $deleted > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateChapterCount(int $bookId, int $count): void
    {
        $this->query()
            ->where($this->primaryKey, '=', $bookId)
            ->updatePrepared(['total_chapters' => $count]);
    }

    /**
     * {@inheritdoc}
     */
    public function updateCurrentChapter(int $bookId, int $chapterNum): void
    {
        $this->query()
            ->where($this->primaryKey, '=', $bookId)
            ->updatePrepared(['current_chapter' => $chapterNum]);
    }

    /**
     * {@inheritdoc}
     */
    public function getChapters(int $bookId): array
    {
        $rows = QueryBuilder::table('texts')
            ->select(['TxID', 'TxChapterNum', 'TxChapterTitle', 'TxTitle'])
            ->where('TxBkID', '=', $bookId)
            ->orderBy('TxChapterNum', 'ASC')
            ->getPrepared();

        return array_map(
            /**
             * @param array<string, mixed> $row
             * @return array{id: int, num: int, title: string}
             */
            fn(array $row) => [
                'id' => (int) $row['TxID'],
                'num' => (int) $row['TxChapterNum'],
                'title' => (string) ($row['TxChapterTitle'] ?? $row['TxTitle']),
            ],
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getChapterTextId(int $bookId, int $chapterNum): ?int
    {
        $row = QueryBuilder::table('texts')
            ->select(['TxID'])
            ->where('TxBkID', '=', $bookId)
            ->where('TxChapterNum', '=', $chapterNum)
            ->firstPrepared();

        return $row !== null ? (int) $row['TxID'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getBookContextForText(int $textId): ?array
    {
        // Get text with book info
        $textRow = QueryBuilder::table('texts')
            ->select(['TxBkID', 'TxChapterNum', 'TxChapterTitle'])
            ->where('TxID', '=', $textId)
            ->firstPrepared();

        if ($textRow === null || !isset($textRow['TxBkID'])) {
            return null;
        }

        $bookId = (int) $textRow['TxBkID'];
        $chapterNum = (int) $textRow['TxChapterNum'];

        // Get book info
        $bookRow = $this->query()
            ->select(['title', 'total_chapters'])
            ->where($this->primaryKey, '=', $bookId)
            ->firstPrepared();

        if ($bookRow === null) {
            return null;
        }

        // Get prev/next chapter text IDs
        $prevRow = QueryBuilder::table('texts')
            ->select(['TxID'])
            ->where('TxBkID', '=', $bookId)
            ->where('TxChapterNum', '<', $chapterNum)
            ->orderBy('TxChapterNum', 'DESC')
            ->limit(1)
            ->firstPrepared();

        $nextRow = QueryBuilder::table('texts')
            ->select(['TxID'])
            ->where('TxBkID', '=', $bookId)
            ->where('TxChapterNum', '>', $chapterNum)
            ->orderBy('TxChapterNum', 'ASC')
            ->limit(1)
            ->firstPrepared();

        return [
            'bookId' => $bookId,
            'bookTitle' => (string) $bookRow['title'],
            'chapterNum' => $chapterNum,
            'chapterTitle' => isset($textRow['TxChapterTitle']) ? (string) $textRow['TxChapterTitle'] : null,
            'totalChapters' => (int) $bookRow['total_chapters'],
            'prevTextId' => $prevRow !== null ? (int) $prevRow['TxID'] : null,
            'nextTextId' => $nextRow !== null ? (int) $nextRow['TxID'] : null,
        ];
    }
}
