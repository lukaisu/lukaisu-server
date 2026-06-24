<?php

/**
 * MySQL Word Tag Association
 *
 * Infrastructure adapter for word-tag associations using MySQL.
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
use Lukaisu\Shared\Infrastructure\Database\DB;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Tags\Domain\TagAssociationInterface;
use Lukaisu\Modules\Tags\Domain\TagRepositoryInterface;

/**
 * MySQL implementation of TagAssociationInterface for word-tag links.
 *
 * Operates on the 'word_tag_map' junction table.
 *
 * @since 3.0.0
 */
class MySqlWordTagAssociation implements TagAssociationInterface
{
    private const TABLE_NAME = 'word_tag_map';
    private const ITEM_COLUMN = 'word_id';
    private const TAG_COLUMN = 'tag_id';

    private TagRepositoryInterface $tagRepository;

    /**
     * Constructor.
     *
     * @param TagRepositoryInterface $tagRepository Term tag repository
     */
    public function __construct(TagRepositoryInterface $tagRepository)
    {
        $this->tagRepository = $tagRepository;
    }

    /**
     * Get a query builder for this association's table.
     *
     * @return QueryBuilder
     */
    private function query(): QueryBuilder
    {
        return QueryBuilder::table(self::TABLE_NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function getTagIdsForItem(int $itemId): array
    {
        $rows = $this->query()
            ->select([self::TAG_COLUMN])
            ->where(self::ITEM_COLUMN, '=', $itemId)
            ->getPrepared();

        return array_map('intval', array_column($rows, self::TAG_COLUMN));
    }

    /**
     * {@inheritdoc}
     */
    public function getTagTextsForItem(int $itemId): array
    {
        $rows = Connection::preparedFetchAll(
            'SELECT text FROM word_tag_map, tags WHERE id = tag_id AND word_id = ? ORDER BY text',
            [$itemId]
        );

        /** @var list<string> */
        return array_column($rows, 'text');
    }

    /**
     * {@inheritdoc}
     */
    public function setTagsForItem(int $itemId, array $tagIds): void
    {
        DB::beginTransaction();
        try {
            // Delete existing associations
            $this->clearTagsForItem($itemId);

            // Insert new associations
            foreach ($tagIds as $tagId) {
                $this->addTag($itemId, $tagId);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setTagsByName(int $itemId, array $tagNames): void
    {
        DB::beginTransaction();
        try {
            // Delete existing associations
            $this->clearTagsForItem($itemId);

            // Create/get tags and associate them
            foreach ($tagNames as $tagName) {
                $tagName = trim($tagName);
                if ($tagName === '') {
                    continue;
                }

                // Get or create the tag for the current user. getOrCreate is
                // user-scoped, so $tagId already points at the right row; using
                // it directly avoids a text re-lookup that would otherwise
                // match another user's tag with the same name.
                $tagId = $this->tagRepository->getOrCreate($tagName);

                Connection::preparedExecute(
                    'INSERT IGNORE INTO word_tag_map (word_id, tag_id) VALUES (?, ?)',
                    [$itemId, $tagId]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addTag(int $itemId, int $tagId): bool
    {
        if ($this->hasTag($itemId, $tagId)) {
            return false;
        }

        $this->query()->insertPrepared([
            self::ITEM_COLUMN => $itemId,
            self::TAG_COLUMN => $tagId,
        ]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function removeTag(int $itemId, int $tagId): bool
    {
        $affected = $this->query()
            ->where(self::ITEM_COLUMN, '=', $itemId)
            ->where(self::TAG_COLUMN, '=', $tagId)
            ->deletePrepared();

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function addTagToItems(int $tagId, array $itemIds): int
    {
        if (empty($itemIds)) {
            return 0;
        }

        $count = 0;
        foreach ($itemIds as $itemId) {
            if ($this->addTag($itemId, $tagId)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function removeTagFromItems(int $tagId, array $itemIds): int
    {
        if (empty($itemIds)) {
            return 0;
        }

        $itemIds = array_map('intval', $itemIds);
        return $this->query()
            ->where(self::TAG_COLUMN, '=', $tagId)
            ->whereIn(self::ITEM_COLUMN, $itemIds)
            ->deletePrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function clearTagsForItem(int $itemId): int
    {
        return $this->query()
            ->where(self::ITEM_COLUMN, '=', $itemId)
            ->deletePrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function clearItemsForTag(int $tagId): int
    {
        return $this->query()
            ->where(self::TAG_COLUMN, '=', $tagId)
            ->deletePrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupOrphanedLinks(): int
    {
        // Delete word_tag_map where the tag no longer exists
        return Connection::preparedExecute(
            'DELETE FROM word_tag_map WHERE tag_id NOT IN (SELECT id FROM tags)',
            []
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getItemCount(int $tagId): int
    {
        return $this->query()
            ->where(self::TAG_COLUMN, '=', $tagId)
            ->count();
    }

    /**
     * {@inheritdoc}
     */
    public function hasTag(int $itemId, int $tagId): bool
    {
        return $this->query()
            ->where(self::ITEM_COLUMN, '=', $itemId)
            ->where(self::TAG_COLUMN, '=', $tagId)
            ->existsPrepared();
    }
}
