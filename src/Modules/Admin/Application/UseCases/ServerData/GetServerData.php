<?php

/**
 * Get Server Data Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\ServerData
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\ServerData;

use Lukaisu\Shared\Infrastructure\ApplicationInfo;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Http\UrlUtilities;

/**
 * Use case for getting server and database information.
 */
class GetServerData
{
    private string $serverSoftware;

    /**
     * Constructor.
     *
     * @param string $serverSoftware Server software string (e.g. from $_SERVER['SERVER_SOFTWARE'])
     */
    public function __construct(string $serverSoftware = 'Unknown')
    {
        $this->serverSoftware = $serverSoftware;
    }

    /**
     * Execute the use case.
     *
     * @return array{
     *   db_name: string,
     *   db_size: float,
     *   server_soft: string,
     *   apache: string,
     *   php: string|false,
     *   mysql: string,
     *   lukaisu_version: string,
     *   server_location: string
     * }
     */
    public function execute(): array
    {
        return [
            'db_name' => Globals::getDatabaseName(),
            'db_size' => $this->getDatabaseSize(),
            'server_soft' => $this->serverSoftware,
            'apache' => $this->parseApacheVersion($this->serverSoftware),
            'php' => phpversion(),
            'mysql' => (string) Connection::fetchValue("SELECT VERSION() AS version", 'version'),
            'lukaisu_version' => ApplicationInfo::getVersionNumber(),
            'server_location' => UrlUtilities::getAppOrigin(),
        ];
    }

    /**
     * Get database size in MB.
     *
     * @return float Database size in MB
     */
    private function getDatabaseSize(): float
    {
        $dbname = Globals::getDatabaseName();

        $tableNames = [
            'feed_links', 'languages', 'news_feeds', 'sentences', 'settings',
            'tags', 'text_tags', 'word_occurrences', 'texts', 'text_tag_map',
            'words', 'word_tag_map'
        ];

        $prefixedTables = array_map(
            fn($table) => Globals::table($table),
            $tableNames
        );

        $placeholders = implode(', ', array_fill(0, count($prefixedTables), '?'));
        $bindings = array_merge([$dbname], $prefixedTables);

        /** @var float|int|string|null $temp_size */
        $temp_size = Connection::preparedFetchValue(
            "SELECT ROUND(SUM(data_length+index_length)/1024/1024, 1) AS size_mb
            FROM information_schema.TABLES
            WHERE table_schema = ?
            AND table_name IN ($placeholders)",
            $bindings,
            'size_mb'
        );

        if ($temp_size === null) {
            return 0.0;
        }

        return (float) $temp_size;
    }

    /**
     * Parse Apache version from server software string.
     *
     * @param string $serverSoft Server software string
     *
     * @return string Apache version string
     */
    private function parseApacheVersion(string $serverSoft): string
    {
        if (str_starts_with($serverSoft, "Apache/")) {
            $temp_soft = explode(' ', $serverSoft);
            return $temp_soft[0];
        }

        return "Apache/?";
    }
}
