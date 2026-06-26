<?php

/**
 * Session Cleaner Service
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\Services;

use Lukaisu\Shared\Infrastructure\Database\Settings;

/**
 * Service for clearing session filters.
 *
 * Called when changing the current language to reset all filters.
 */
class SessionCleaner
{
    /**
     * Clear settings when changing the current language.
     *
     * Note: Pagination/filter state is now stored in URL parameters,
     * so session clearing is no longer needed. This method only clears
     * database settings that should reset on language change.
     *
     * @return void
     */
    public function clearAllFilters(): void
    {
        // Clear current text setting (database-stored)
        Settings::savePerUser('currenttext', '');
    }
}
