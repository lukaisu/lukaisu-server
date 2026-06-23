<?php

/**
 * Get Setting Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\Settings
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\Settings;

use Lukaisu\Modules\Admin\Domain\SettingsRepositoryInterface;

/**
 * Use case for getting a single setting value.
 *
 * @since 3.0.0
 */
class GetSetting
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
     * @param string $key     Setting key
     * @param string $default Default value if not found
     *
     * @return string Setting value
     */
    public function execute(string $key, string $default = ''): string
    {
        return $this->repository->get($key, $default);
    }
}
