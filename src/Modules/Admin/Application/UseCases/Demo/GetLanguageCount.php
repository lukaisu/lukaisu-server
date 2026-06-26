<?php

/**
 * Get Language Count Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\Demo
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\Demo;

use Lukaisu\Modules\Admin\Infrastructure\MySqlStatisticsRepository;

/**
 * Use case for getting language count.
 *
 * Used to warn users before installing demo database.
 */
class GetLanguageCount
{
    private MySqlStatisticsRepository $repository;

    /**
     * Constructor.
     *
     * @param MySqlStatisticsRepository|null $repository Statistics repository
     */
    public function __construct(?MySqlStatisticsRepository $repository = null)
    {
        $this->repository = $repository ?? new MySqlStatisticsRepository();
    }

    /**
     * Execute the use case.
     *
     * @return int Language count
     */
    public function execute(): int
    {
        return $this->repository->getLanguageCount();
    }
}
