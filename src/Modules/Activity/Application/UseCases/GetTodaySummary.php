<?php

/**
 * Get Today Summary Use Case
 *
 * PHP version 8.2
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Activity\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Activity\Application\UseCases;

use Lukaisu\Modules\Activity\Domain\ActivityRepositoryInterface;

/**
 * Returns today's activity counters.
 */
class GetTodaySummary
{
    private ActivityRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param ActivityRepositoryInterface $repository Activity repository
     */
    public function __construct(ActivityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the use case.
     *
     * @return array{terms_created: int, terms_reviewed: int, texts_read: int}
     */
    public function execute(): array
    {
        return $this->repository->getTodaySummary();
    }
}
