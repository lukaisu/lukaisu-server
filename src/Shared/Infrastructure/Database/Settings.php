<?php

/**
 * \file
 * \brief Application settings management.
 *
 * PHP version 8.1
 *
 * @category Database
 * @package  Lukaisu
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Infrastructure\Database;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Utilities\ErrorHandler;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Admin\Domain\SettingDefinitions;

/**
 * Application settings management.
 *
 * Provides methods for reading, writing, and managing application settings
 * stored in the database, as well as Lukaisu Server general table operations.
 *
 * @since 3.0.0
 */
class Settings
{
    /**
     * Convert a setting to 0 or 1.
     *
     * @param string     $key The setting key
     * @param string|int $dft Default value to use, should be convertible to string
     *
     * @return int
     *
     * @psalm-return 0|1
     */
    public static function getZeroOrOne(string $key, string|int $dft): int
    {
        $r = self::get($key);
        if ($r === '') {
            return (int)$dft !== 0 ? 1 : 0;
        }
        return (int)$r !== 0 ? 1 : 0;
    }

    /**
     * Get a setting from the database. It can also check for its validity.
     *
     * @param string $key Setting key. If $key is 'currentlanguage' or
     *                    'currenttext', we validate language/text.
     *
     * @return string Value in the database if found, or an empty string
     */
    public static function get(string $key): string
    {
        if ($key === '') {
            return '';
        }
        $val = QueryBuilder::table('settings')
            ->where('name', '=', $key)
            ->valuePrepared('value');
        if (isset($val)) {
            $val = trim((string) $val);
            if ($key == 'currentlanguage') {
                $val = Validation::language($val);
            }
            if ($key == 'currenttext') {
                $val = Validation::text($val);
            }
            return $val;
        }
        return '';
    }

    /**
     * Get the settings value for a specific key. Return a default value when possible.
     *
     * In multi-user mode, user-scoped settings are checked for the current user
     * first, then fall through to the hardcoded default — never to the global
     * `user_id=0` row, since that would leak the prior user's choice into a fresh
     * account. Admin-scoped settings still fall back to `user_id=0` (that row is
     * how admins set system-wide values), and single-user mode (no current
     * user) keeps the original `user_id=0` → hardcoded-default chain.
     *
     * @param string $key Settings key
     *
     * @return string Requested setting, or default value, or ''
     */
    public static function getWithDefault(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        $isUserScope = SettingDefinitions::getScope($key) === SettingDefinitions::SCOPE_USER;

        // For user-scoped settings in multi-user mode, try user-specific row first
        if ($userId !== null && $isUserScope) {
            try {
                $val = (string) Connection::preparedFetchValue(
                    "SELECT value FROM settings WHERE name = ? AND user_id = ?",
                    [$key, $userId],
                    'value'
                );
                if ($val !== '') {
                    return trim($val);
                }
            } catch (\RuntimeException $e) {
                // DB not available — fall through
            }

            // Skip the user_id=0 fallback for SCOPE_USER keys when a user is
            // logged in: that row is the previous user's saved value, not a
            // legitimate default for this account.
            return SettingDefinitions::getDefault($key) ?? '';
        }

        // Fall back to global row (user_id=0)
        try {
            $val = (string) QueryBuilder::table('settings')
                ->where('name', '=', $key)
                ->where('user_id', '=', 0)
                ->valuePrepared('value');
            if ($val != '') {
                return trim($val);
            }
        } catch (\RuntimeException $e) {
            // DB not available — fall through to default
        }
        return SettingDefinitions::getDefault($key) ?? '';
    }

    /**
     * Save the setting identified by a key with a specific value.
     *
     * @param string $k Setting key
     * @param mixed  $v Setting value, will get converted to string
     *
     * @return void
     *
     * @throws \InvalidArgumentException If value is not set or is empty
     */
    public static function save(string $k, mixed $v): void
    {
        $defs = SettingDefinitions::getAll();
        if (!isset($v)) {
            throw new \InvalidArgumentException('Value is not set');
        }
        if (SettingDefinitions::isNumeric($k)) {
            $v = (int)$v;
            $default = SettingDefinitions::getDefault($k) ?? '';
            if (isset($defs[$k]['min']) && $v < $defs[$k]['min']) {
                $v = $default;
            }
            if (isset($defs[$k]['max']) && $v > $defs[$k]['max']) {
                $v = $default;
            }
        }
        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert
        // Settings table has composite primary key (name, user_id)
        // user_id defaults to 0 for single-user mode
        Connection::preparedExecute(
            "INSERT INTO settings (name, user_id, value) VALUES (?, 0, ?)
             ON DUPLICATE KEY UPDATE value = ?",
            [$k, (string)$v, (string)$v]
        );
    }

    /**
     * Save a setting under the current user's scope when multi-user mode is
     * enabled, or globally otherwise.
     *
     * `Settings::get()` already reads through QueryBuilder, which auto-scopes
     * the `settings` table; in multi-user mode that means a `Settings::save`
     * to `user_id=0` is invisible to the reader and the value is silently lost
     * (or worse, overwrites the global default seen by users with no
     * per-user row). This helper picks the matching write path so reads find
     * the value the current request just stored.
     *
     * @param string $k Setting key
     * @param mixed  $v Setting value, will get converted to string
     */
    public static function savePerUser(string $k, mixed $v): void
    {
        $userId = Globals::isMultiUserEnabled() ? Globals::getCurrentUserId() : null;
        if ($userId !== null) {
            self::saveForUser($k, $v, $userId);
        } else {
            self::save($k, $v);
        }
    }

    /**
     * Save a user-scoped setting for a specific user.
     *
     * Uses user_id = $userId for per-user storage.
     *
     * @param string $k      Setting key
     * @param mixed  $v      Setting value
     * @param int    $userId User ID
     *
     * @return void
     */
    public static function saveForUser(string $k, mixed $v, int $userId): void
    {
        $defs = SettingDefinitions::getAll();
        if (!isset($v)) {
            throw new \InvalidArgumentException('Value is not set');
        }
        if (SettingDefinitions::isNumeric($k)) {
            $v = (int)$v;
            $default = SettingDefinitions::getDefault($k) ?? '';
            if (isset($defs[$k]['min']) && $v < $defs[$k]['min']) {
                $v = $default;
            }
            if (isset($defs[$k]['max']) && $v > $defs[$k]['max']) {
                $v = $default;
            }
        }
        Connection::preparedExecute(
            "INSERT INTO settings (name, user_id, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = ?",
            [$k, $userId, (string)$v, (string)$v]
        );
    }

    /**
     * Check if the _lukaisugeneral table exists, create it if not.
     *
     * @return void
     */
    public static function lukaisuTableCheck(): void
    {
        $tables = Connection::fetchAll("SHOW TABLES LIKE '\\_lukaisugeneral'");
        if (empty($tables)) {
            Connection::execute(
                "CREATE TABLE IF NOT EXISTS _lukaisugeneral (
                    LukaisuKey varchar(40) NOT NULL,
                    LukaisuValue varchar(40) DEFAULT NULL,
                    PRIMARY KEY (LukaisuKey)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8"
            );
            $tables2 = Connection::fetchAll("SHOW TABLES LIKE '\\_lukaisugeneral'");
            if (empty($tables2)) {
                ErrorHandler::die("Unable to create table '_lukaisugeneral'!");
            }
        }
    }

    /**
     * Set a value in the _lukaisugeneral table.
     *
     * @param string $key Key to set
     * @param string $val Value to store
     *
     * @return void
     */
    public static function lukaisuTableSet(string $key, string $val): void
    {
        self::lukaisuTableCheck();
        Connection::preparedExecute(
            "INSERT INTO _lukaisugeneral (LukaisuKey, LukaisuValue) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE LukaisuValue = ?",
            [$key, $val, $val]
        );
    }

    /**
     * Get a value from the _lukaisugeneral table.
     *
     * @param string $key Key to retrieve
     *
     * @return string Value or empty string if not found
     */
    public static function lukaisuTableGet(string $key): string
    {
        self::lukaisuTableCheck();
        return (string)Connection::preparedFetchValue(
            "SELECT LukaisuValue as value
            FROM _lukaisugeneral
            WHERE LukaisuKey = ?",
            [$key]
        );
    }
}
