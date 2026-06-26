<?php

/**
 * Get Tag By ID Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Tags\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Tags\Application\UseCases;

use Lukaisu\Modules\Tags\Domain\Tag;
use Lukaisu\Modules\Tags\Domain\TagRepositoryInterface;

/**
 * Use case for retrieving a tag by ID.
 */
class GetTagById
{
    private TagRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param TagRepositoryInterface $repository Tag repository
     */
    public function __construct(TagRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the get tag by ID use case.
     *
     * @param int $id Tag ID
     *
     * @return Tag|null Tag entity or null if not found
     */
    public function execute(int $id): ?Tag
    {
        return $this->repository->find($id);
    }

    /**
     * Check if a tag exists.
     *
     * @param int $id Tag ID
     *
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->repository->exists($id);
    }

    /**
     * Get tag as array (backward compatible).
     *
     * @param int $id Tag ID
     *
     * @return array|null Tag data or null if not found
     */
    public function executeAsArray(int $id): ?array
    {
        $tag = $this->execute($id);

        if ($tag === null) {
            return null;
        }

        return [
            'id' => $tag->id()->toInt(),
            'text' => $tag->text(),
            'comment' => $tag->comment(),
        ];
    }
}
