<?php

/**
 * Test Connection Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\Wizard
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\Wizard;

use Lukaisu\Modules\Admin\Application\DTO\DatabaseConnectionDTO;
use Lukaisu\Modules\Admin\Infrastructure\FileSystemEnvRepository;

/**
 * Use case for testing database connection.
 *
 * This use case does NOT require an existing database connection.
 * It attempts to establish a new connection using provided credentials.
 */
class TestConnection
{
    private FileSystemEnvRepository $repository;

    /**
     * Constructor.
     *
     * @param FileSystemEnvRepository|null $repository Env file repository
     */
    public function __construct(?FileSystemEnvRepository $repository = null)
    {
        $this->repository = $repository ?? new FileSystemEnvRepository();
    }

    /**
     * Execute the use case.
     *
     * @param DatabaseConnectionDTO $connection Connection data to test
     *
     * @return array{success: bool, error: ?string}
     */
    public function execute(DatabaseConnectionDTO $connection): array
    {
        return $this->repository->testConnection($connection);
    }

    /**
     * Execute with form data array.
     *
     * @param array<string, mixed> $formData Form input data
     *
     * @return array{success: bool, error: ?string}
     */
    public function executeFromForm(array $formData): array
    {
        $dto = DatabaseConnectionDTO::fromFormData($formData);
        return $this->repository->testConnection($dto);
    }
}
