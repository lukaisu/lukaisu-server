<?php

/**
 * Get User Preferences Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\User\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\User\Application\UseCases;

use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Modules\Admin\Domain\SettingDefinitions;

/**
 * Use case for getting all user-scoped preferences.
 */
class GetUserPreferences
{
    /**
     * Execute the use case.
     *
     * @return array<string, string> User preferences with their current values
     */
    public function execute(): array
    {
        $settings = [];
        foreach (SettingDefinitions::getUserKeys() as $key) {
            $settings[$key] = Settings::getWithDefault($key);
        }
        return $settings;
    }
}
