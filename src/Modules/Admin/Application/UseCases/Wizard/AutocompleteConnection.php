<?php

/**
 * Autocomplete Connection Use Case
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
 * Use case for auto-filling connection with server defaults.
 *
 * This use case does NOT require database access.
 */
class AutocompleteConnection
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
     * Returns connection data pre-filled with server environment values.
     *
     * @return DatabaseConnectionDTO Pre-filled connection data
     */
    public function execute(): DatabaseConnectionDTO
    {
        return $this->repository->getAutocompleteSuggestions();
    }
}
