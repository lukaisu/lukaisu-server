<?php

/**
 * MySQL Settings Repository
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Modules\Admin\Domain\SettingsRepositoryInterface;

/**
 * MySQL repository for settings operations.
 *
 * Provides database access for application settings.
 */
class MySqlSettingsRepository implements SettingsRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function get(string $key, string $default = ''): string
    {
        return Settings::getWithDefault($key);
    }

    /**
     * {@inheritdoc}
     */
    public function save(string $key, string $value): void
    {
        Settings::save($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByPattern(string $pattern): int
    {
        return QueryBuilder::table('settings')
            ->where('name', 'LIKE', $pattern)
            ->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        $rows = QueryBuilder::table('settings')
            ->select(['name', 'value'])
            ->getPrepared();

        /** @var array<string, string> $settings */
        $settings = [];
        foreach ($rows as $row) {
            $key = (string) ($row['name'] ?? '');
            $settings[$key] = (string) ($row['value'] ?? '');
        }

        return $settings;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        return QueryBuilder::table('settings')
            ->where('name', '=', $key)
            ->existsPrepared();
    }
}
