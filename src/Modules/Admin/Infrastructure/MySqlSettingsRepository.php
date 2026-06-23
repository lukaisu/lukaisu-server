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
 * @since    3.0.0
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
 *
 * @since 3.0.0
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
            ->where('StKey', 'LIKE', $pattern)
            ->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        $rows = QueryBuilder::table('settings')
            ->select(['StKey', 'StValue'])
            ->getPrepared();

        /** @var array<string, string> $settings */
        $settings = [];
        foreach ($rows as $row) {
            $key = (string) ($row['StKey'] ?? '');
            $settings[$key] = (string) ($row['StValue'] ?? '');
        }

        return $settings;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        return QueryBuilder::table('settings')
            ->where('StKey', '=', $key)
            ->existsPrepared();
    }
}
