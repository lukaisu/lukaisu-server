<?php

/**
 * Empty Database Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\Backup
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\Backup;

use Lukaisu\Modules\Admin\Domain\BackupRepositoryInterface;

/**
 * Use case for emptying the database.
 *
 * Truncates all user data tables while preserving settings.
 *
 * @since 3.0.0
 */
class EmptyDatabase
{
    private BackupRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param BackupRepositoryInterface $repository Backup repository
     */
    public function __construct(BackupRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the use case.
     *
     * @return array{success: bool}
     */
    public function execute(): array
    {
        $this->repository->truncateUserTables();
        return ['success' => true];
    }
}
