<?php

/**
 * Reset All Settings Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\Settings
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\Settings;

use Lukaisu\Modules\Admin\Domain\SettingsRepositoryInterface;

/**
 * Use case for resetting all settings to defaults.
 */
class ResetAllSettings
{
    private SettingsRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param SettingsRepositoryInterface $repository Settings repository
     */
    public function __construct(SettingsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the use case.
     *
     * Deletes all settings with 'set-' prefix, restoring defaults.
     *
     * @return array{success: bool}
     */
    public function execute(): array
    {
        $this->repository->deleteByPattern('set-%');
        return ['success' => true];
    }
}
