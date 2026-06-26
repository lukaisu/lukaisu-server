<?php

/**
 * Get Tomorrow Count Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Review\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Review\Application\UseCases;

use Lukaisu\Modules\Review\Domain\ReviewRepositoryInterface;
use Lukaisu\Modules\Review\Domain\ReviewConfiguration;

/**
 * Use case for getting count of words due tomorrow.
 */
class GetTomorrowCount
{
    private ReviewRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param ReviewRepositoryInterface $repository Review repository
     */
    public function __construct(ReviewRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get count of words due tomorrow.
     *
     * @param ReviewConfiguration $config Test configuration
     *
     * @return array{count: int}
     */
    public function execute(ReviewConfiguration $config): array
    {
        if (!$config->isValid()) {
            return ['count' => 0];
        }

        return [
            'count' => $this->repository->getTomorrowCount($config)
        ];
    }
}
