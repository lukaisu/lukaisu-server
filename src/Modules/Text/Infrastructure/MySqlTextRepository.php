<?php

/**
 * MySQL Text Repository
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Infrastructure;

use Lukaisu\Shared\Infrastructure\Repository\AbstractRepository;
use Lukaisu\Modules\Text\Domain\Text;
use Lukaisu\Modules\Text\Domain\TextRepositoryInterface;
use Lukaisu\Modules\Text\Domain\ValueObject\TextId;

/**
 * MySQL repository for Text entities.
 *
 * Provides database access for text management operations.
 * Handles both basic CRUD and text-specific queries.
 *
 * @extends AbstractRepository<Text>
 */
class MySqlTextRepository extends AbstractRepository implements TextRepositoryInterface
{
    /**
     * @var string Table name without prefix
     */
    protected string $tableName = 'texts';

    /**
     * @var string Primary key column
     */
    protected string $primaryKey = 'id';

    /**
     * @var array<string, string> Property to column mapping
     */
    protected array $columnMap = [
        'id' => 'id',
        'languageId' => 'language_id',
        'title' => 'title',
        'text' => 'text',
        'annotatedText' => 'annotated_text',
        'mediaUri' => 'audio_uri',
        'sourceUri' => 'source_uri',
        'position' => 'position',
        'audioPosition' => 'audio_position',
    ];

    /**
     * Override base query to filter out archived texts.
     *
     * Active texts have archived_at IS NULL. Archived texts use
     * a separate set of methods that explicitly include archived_at IS NOT NULL.
     *
     * @return \Lukaisu\Shared\Infrastructure\Database\QueryBuilder
     */
    protected function query(): \Lukaisu\Shared\Infrastructure\Database\QueryBuilder
    {
        return \Lukaisu\Shared\Infrastructure\Database\QueryBuilder::table($this->tableName)
            ->whereNull('archived_at');
    }

    /**
     * {@inheritdoc}
     */
    protected function mapToEntity(array $row): Text
    {
        return Text::reconstitute(
            (int) $row['id'],
            (int) $row['language_id'],
            (string) $row['title'],
            (string) $row['text'],
            (string) ($row['annotated_text'] ?? ''),
            (string) ($row['audio_uri'] ?? ''),
            (string) ($row['source_uri'] ?? ''),
            (int) ($row['position'] ?? 0),
            (float) ($row['audio_position'] ?? 0.0)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param Text $entity
     *
     * @return array<string, mixed>
     */
    protected function mapToRow(object $entity): array
    {
        return [
            'id' => $entity->id()->toInt(),
            'language_id' => $entity->languageId()->toInt(),
            'title' => $entity->title(),
            'text' => $entity->text(),
            'annotated_text' => $entity->annotatedText(),
            'audio_uri' => $entity->mediaUri(),
            'source_uri' => $entity->sourceUri(),
            'position' => $entity->position(),
            'audio_position' => $entity->audioPosition(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param Text $entity
     */
    protected function getEntityId(object $entity): int
    {
        return $entity->id()->toInt();
    }

    /**
     * {@inheritdoc}
     *
     * @param Text $entity
     */
    protected function setEntityId(object $entity, int $id): void
    {
        $entity->setId(TextId::fromInt($id));
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Text
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
    public function findAll(): array
    {
        $rows = $this->query()->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findByLanguage(
        int $languageId,
        ?string $orderBy = 'title',
        string $direction = 'ASC'
    ): array {
        $rows = $this->query()
            ->where('language_id', '=', $languageId)
            ->orderBy($orderBy ?? 'title', $direction)
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findByTitle(int $languageId, string $title): ?Text
    {
        $row = $this->query()
            ->where('language_id', '=', $languageId)
            ->where('title', '=', $title)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function titleExists(int $languageId, string $title, ?int $excludeId = null): bool
    {
        $query = $this->query()
            ->where('language_id', '=', $languageId)
            ->where('title', '=', $title);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function countByLanguage(int $languageId): int
    {
        return $this->query()
            ->where('language_id', '=', $languageId)
            ->countPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function getForSelect(int $languageId = 0, int $maxNameLength = 40): array
    {
        $query = $this->query()
            ->select(['id', 'title', 'language_id'])
            ->orderBy('title');

        if ($languageId > 0) {
            $query->where('language_id', '=', $languageId);
        }

        $rows = $query->getPrepared();
        $result = [];

        foreach ($rows as $row) {
            $title = (string) $row['title'];
            if (mb_strlen($title, 'UTF-8') > $maxNameLength) {
                $title = mb_substr($title, 0, $maxNameLength, 'UTF-8') . '...';
            }
            $result[] = [
                'id' => (int) $row['id'],
                'title' => $title,
                'language_id' => (int) $row['language_id'],
            ];
        }

        return $result;
    }

    /**
     * Find texts with media (audio/video).
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return Text[]
     */
    public function findWithMedia(?int $languageId = null): array
    {
        $query = $this->query()
            ->where('audio_uri', '!=', '')
            ->orderBy('title');

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
     * Find annotated texts.
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return Text[]
     */
    public function findAnnotated(?int $languageId = null): array
    {
        $query = $this->query()
            ->where('annotated_text', '!=', '')
            ->orderBy('title');

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
     * Find unannotated texts.
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return Text[]
     */
    public function findUnannotated(?int $languageId = null): array
    {
        $query = $this->query()
            ->where('annotated_text', '=', '')
            ->orderBy('title');

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
    public function updatePosition(int $textId, int $position): bool
    {
        $affected = $this->query()
            ->where('id', '=', $textId)
            ->updatePrepared(['position' => max(0, $position)]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateAudioPosition(int $textId, float $position): bool
    {
        $affected = $this->query()
            ->where('id', '=', $textId)
            ->updatePrepared(['audio_position' => max(0.0, $position)]);

        return $affected > 0;
    }

    /**
     * Reset reading progress for a text.
     *
     * @param int $textId Text ID
     *
     * @return bool True if updated
     */
    public function resetProgress(int $textId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $textId)
            ->updatePrepared(
                [
                'position' => 0,
                'audio_position' => 0.0,
                ]
            );

        return $affected > 0;
    }

    /**
     * Clear the annotated text for a text.
     *
     * @param int $textId Text ID
     *
     * @return bool True if updated
     */
    public function clearAnnotation(int $textId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $textId)
            ->updatePrepared(['annotated_text' => '']);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreviousTextId(int $textId, int $languageId): ?int
    {
        $row = $this->query()
            ->select('id')
            ->where('language_id', '=', $languageId)
            ->where('id', '<', $textId)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->firstPrepared();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getNextTextId(int $textId, int $languageId): ?int
    {
        $row = $this->query()
            ->select('id')
            ->where('language_id', '=', $languageId)
            ->where('id', '>', $textId)
            ->orderBy('id', 'ASC')
            ->limit(1)
            ->firstPrepared();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getBasicInfo(int $textId): ?array
    {
        $row = $this->query()
            ->select(
                [
                'id',
                'title',
                'language_id',
                'audio_uri',
                'LENGTH(annotated_text) AS annotlen'
                ]
            )
            ->where('id', '=', $textId)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'language_id' => (int) $row['language_id'],
            'has_media' => !empty($row['audio_uri']),
            'has_annotation' => !empty($row['annotlen']),
        ];
    }

    /**
     * Get texts with pagination and language info.
     *
     * @param int    $languageId Language ID (0 for all)
     * @param int    $page       Page number (1-based)
     * @param int    $perPage    Items per page
     * @param string $orderBy    Column to order by
     * @param string $direction  Sort direction
     *
     * @return array{items: Text[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function findPaginated(
        int $languageId = 0,
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'title',
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
     * Search texts by title.
     *
     * @param string   $query      Search query
     * @param int|null $languageId Language ID (null for all)
     * @param int      $limit      Maximum results
     *
     * @return Text[]
     */
    public function searchByTitle(string $query, ?int $languageId = null, int $limit = 50): array
    {
        $searchPattern = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

        $dbQuery = $this->query()
            ->where('title', 'LIKE', $searchPattern)
            ->orderBy('title')
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
     * Get language IDs that have texts.
     *
     * @return int[] Array of language IDs
     */
    public function getLanguagesWithTexts(): array
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
     * Get statistics for texts.
     *
     * @param int|null $languageId Language ID (null for all)
     *
     * @return array{total: int, with_media: int, annotated: int, with_source: int}
     */
    public function getStatistics(?int $languageId = null): array
    {
        $baseQuery = $this->query();
        if ($languageId !== null) {
            $baseQuery->where('language_id', '=', $languageId);
        }

        $total = (clone $baseQuery)->countPrepared();

        $withMedia = (clone $baseQuery)
            ->where('audio_uri', '!=', '')
            ->countPrepared();

        $annotated = (clone $baseQuery)
            ->where('annotated_text', '!=', '')
            ->countPrepared();

        $withSource = (clone $baseQuery)
            ->where('source_uri', '!=', '')
            ->countPrepared();

        return [
            'total' => $total,
            'with_media' => $withMedia,
            'annotated' => $annotated,
            'with_source' => $withSource,
        ];
    }
}
