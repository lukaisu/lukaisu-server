<?php

/**
 * \file
 * \brief Helper for adding user scope to raw SQL queries.
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

/**
 * Helper class for adding user scope filtering to raw SQL queries.
 *
 * Use this class when you need to execute raw SQL but still want
 * automatic user scope filtering. For most cases, prefer using
 * QueryBuilder which handles this automatically.
 *
 * Usage:
 * ```php
 * // Get user scope condition for WHERE clause (for raw SQL)
 * $sql = "SELECT * FROM words WHERE language_id = 1"
 *      . UserScopedQuery::forTable('words');
 *
 * // For prepared statements
 * $bindings = [1]; // language_id = 1
 * $sql = "SELECT * FROM words WHERE language_id = ?"
 *      . UserScopedQuery::forTablePrepared('words', $bindings);
 *
 * // Get user_id for INSERT (when using raw SQL)
 * $userId = UserScopedQuery::getUserIdForInsert('words');
 * if ($userId !== null) {
 *     $data['user_id'] = $userId;
 * }
 * ```
 *
 * @category Database
 * @package  Lukaisu\Database
 * @since    3.0.0
 */
class UserScopedQuery
{
    /**
     * Mapping of user-scoped tables to their user ID column.
     *
     * @var array<string, string>
     */
    private const USER_SCOPED_TABLES = [
        'languages' => 'user_id',
        'texts' => 'user_id',
        'words' => 'user_id',
        'tags' => 'user_id',
        'text_tags' => 'user_id',
        'news_feeds' => 'user_id',
        'settings' => 'user_id',
        'local_dictionaries' => 'LdUsID',
        'activity_log' => 'user_id',
        'books' => 'user_id',
    ];

    /**
     * Get the user ID column name for a table.
     *
     * @param string $tableName The table name (without prefix)
     *
     * @return string|null The column name or null if not user-scoped
     */
    public static function getUserIdColumn(string $tableName): ?string
    {
        return self::USER_SCOPED_TABLES[$tableName] ?? null;
    }

    /**
     * Check if a table is user-scoped.
     *
     * @param string $tableName The table name (without prefix)
     *
     * @return bool True if the table requires user_id filtering
     */
    public static function isUserScopedTable(string $tableName): bool
    {
        return isset(self::USER_SCOPED_TABLES[$tableName]);
    }

    /**
     * Get the user ID value to use for inserts.
     *
     * Returns the current user ID when multi-user mode is enabled
     * and a user is authenticated. Returns null otherwise.
     *
     * @param string $tableName The table name (without prefix)
     *
     * @return int|null The user ID or null
     */
    public static function getUserIdForInsert(string $tableName): ?int
    {
        if (!Globals::isMultiUserEnabled()) {
            return null;
        }

        if (!self::isUserScopedTable($tableName)) {
            return null;
        }

        return Globals::getCurrentUserId();
    }

    /**
     * Get INSERT column fragment for user scope.
     *
     * Returns a SQL fragment like ", user_id" to append to INSERT column list
     * when user scope should be applied, or empty string otherwise.
     *
     * Usage:
     * ```php
     * $sql = "INSERT INTO words (language_id, text" . UserScopedQuery::insertColumn('words') .
     *         ") VALUES (?, ?" . UserScopedQuery::insertValuePrepared('words', $bindings) . ")";
     * ```
     *
     * @param string $tableName The table name (without prefix)
     *
     * @return string SQL column fragment (includes leading comma) or empty string
     */
    public static function insertColumn(string $tableName): string
    {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $column = self::getUserIdColumn($tableName);
        if ($column === null) {
            return '';
        }

        return ", {$column}";
    }

    /**
     * Get INSERT value fragment for user scope (prepared statement version).
     *
     * Returns a SQL fragment like ", ?" and adds the user ID to bindings
     * when user scope should be applied, or empty string otherwise.
     *
     * @param string            $tableName The table name (without prefix)
     * @param array<int, mixed> &$bindings Reference to bindings array
     *
     * @return string SQL value fragment (includes leading comma) or empty string
     */
    public static function insertValuePrepared(string $tableName, array &$bindings): string
    {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $column = self::getUserIdColumn($tableName);
        if ($column === null) {
            return '';
        }

        $bindings[] = $userId;
        return ', ?';
    }

    /**
     * Get INSERT value fragment for user scope (raw SQL version).
     *
     * Returns a SQL fragment like ", 1" with the actual user ID value
     * when user scope should be applied, or empty string otherwise.
     *
     * @param string $tableName The table name (without prefix)
     *
     * @return string SQL value fragment (includes leading comma) or empty string
     */
    public static function insertValue(string $tableName): string
    {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $column = self::getUserIdColumn($tableName);
        if ($column === null) {
            return '';
        }

        return ', ' . $userId;
    }

    /**
     * Get WHERE condition for user scope filtering.
     *
     * Returns a SQL fragment like " AND user_id = 1" when user scope
     * should be applied, or empty string otherwise.
     *
     * @param string $tableName    The table name (without prefix)
     * @param string $alias        Optional table alias to prefix the column
     * @param string $_parentTable Optional parent table for inherited scope (unused, kept for API compat)
     *
     * @return string SQL WHERE condition fragment (includes leading AND)
     */
    public static function forTable(string $tableName, string $alias = '', string $_parentTable = ''): string
    {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $column = self::getUserIdColumn($tableName);
        if ($column === null) {
            return '';
        }

        $columnRef = $alias !== '' ? "{$alias}.{$column}" : $column;
        return " AND {$columnRef} = " . $userId;
    }

    /**
     * Get WHERE condition for user scope filtering (prepared statement version).
     *
     * Returns a SQL fragment like " AND user_id = ?" and adds the user ID
     * to the provided bindings array.
     *
     * @param string             $tableName    The table name (without prefix)
     * @param array<int, mixed>  &$bindings    Reference to bindings array
     * @param string             $alias        Optional table alias
     * @param string             $_parentTable Optional parent table for inherited scope (unused, kept for API compat)
     *
     * @return string SQL WHERE condition fragment (includes leading AND)
     */
    public static function forTablePrepared(
        string $tableName,
        array &$bindings,
        string $alias = '',
        string $_parentTable = ''
    ): string {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $column = self::getUserIdColumn($tableName);
        if ($column === null) {
            return '';
        }

        $columnRef = $alias !== '' ? "{$alias}.{$column}" : $column;
        $bindings[] = $userId;
        return " AND {$columnRef} = ?";
    }

    /**
     * Get a standalone WHERE clause for user scope.
     *
     * Returns "WHERE user_id = 1" when applicable, empty string otherwise.
     * Use this when you need a WHERE clause that only contains user scope.
     *
     * @param string $tableName The table name (without prefix)
     * @param string $alias     Optional table alias
     *
     * @return string SQL WHERE clause or empty string
     */
    public static function whereClause(string $tableName, string $alias = ''): string
    {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $column = self::getUserIdColumn($tableName);
        if ($column === null) {
            return '';
        }

        $columnRef = $alias !== '' ? "{$alias}.{$column}" : $column;
        return "WHERE {$columnRef} = " . $userId;
    }

    /**
     * Get a standalone WHERE clause (prepared statement version).
     *
     * @param string            $tableName The table name (without prefix)
     * @param array<int, mixed> &$bindings Reference to bindings array
     * @param string            $alias     Optional table alias
     *
     * @return string SQL WHERE clause or empty string
     */
    public static function whereClausePrepared(
        string $tableName,
        array &$bindings,
        string $alias = ''
    ): string {
        if (!Globals::isMultiUserEnabled()) {
            return '';
        }

        $userId = Globals::getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $column = self::getUserIdColumn($tableName);
        if ($column === null) {
            return '';
        }

        $columnRef = $alias !== '' ? "{$alias}.{$column}" : $column;
        $bindings[] = $userId;
        return "WHERE {$columnRef} = ?";
    }

    /**
     * Get the list of all user-scoped tables.
     *
     * @return array<string, string> Table name => user ID column mapping
     */
    public static function getUserScopedTables(): array
    {
        return self::USER_SCOPED_TABLES;
    }
}
