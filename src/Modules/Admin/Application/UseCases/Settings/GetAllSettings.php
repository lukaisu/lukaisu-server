<?php

/**
 * Get All Settings Use Case
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

use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Modules\Admin\Domain\SettingDefinitions;

/**
 * Use case for getting all admin-scoped settings.
 *
 * Only returns server-wide admin settings (theme, feed limits, registration).
 * User-scoped preferences are handled by GetUserPreferences in the User module.
 */
class GetAllSettings
{
    /**
     * Execute the use case.
     *
     * @return array<string, string> Admin settings with their values
     */
    public function execute(): array
    {
        $settings = [];
        foreach (SettingDefinitions::getAdminKeys() as $key) {
            $settings[$key] = Settings::getWithDefault($key);
        }
        return $settings;
    }

    /**
     * Get all admin setting keys.
     *
     * @return string[] Setting keys
     */
    public static function getSettingKeys(): array
    {
        return SettingDefinitions::getAdminKeys();
    }
}
