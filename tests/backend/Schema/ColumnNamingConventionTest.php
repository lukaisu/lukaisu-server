<?php

/**
 * Guard test for the snake_case column-naming convention.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Tests\Schema
 * @license  Unlicense <http://unlicense.org/>
 */

declare(strict_types=1);

namespace Lukaisu\Tests\Schema;

use PHPUnit\Framework\TestCase;

/**
 * Ensures the baseline schema carries no legacy Hungarian-prefix column names.
 *
 * The column-naming modernisation (see docs-src/developer/schema-naming.md)
 * renamed every cryptic prefix (WoText, Ti2WoID, LgName, LdLgID, ...) to plain
 * table-scoped snake_case so the database speaks the same vocabulary as the API
 * and the offline mirror. This test parses db/schema/baseline.sql and fails if a
 * column is reintroduced with a non-snake_case name, which would re-expose a raw
 * prefix through the REST API.
 *
 * It is a pure file parse (no database), so it runs on CI without a MySQL
 * service. Index/key names are intentionally not checked: they are internal DDL
 * and never surface in query results or API payloads.
 */
final class ColumnNamingConventionTest extends TestCase
{
    /**
     * SQL keywords that begin a non-column line inside a CREATE TABLE body.
     *
     * @var list<string>
     */
    private const DDL_KEYWORDS = [
        'PRIMARY', 'UNIQUE', 'KEY', 'FOREIGN', 'CONSTRAINT', 'INDEX',
        'FULLTEXT', 'SPATIAL', 'CHECK', 'REFERENCES',
    ];

    /**
     * Every column declared in baseline.sql must be snake_case.
     *
     * @return void
     */
    public function testBaselineColumnsAreSnakeCase(): void
    {
        $columns = $this->baselineColumns();
        $this->assertNotEmpty($columns, 'No columns parsed from baseline.sql');

        $offenders = [];
        foreach ($columns as $table => $names) {
            foreach ($names as $name) {
                if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                    $offenders[] = "$table.$name";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Non-snake_case column names found (legacy prefixes must be renamed): "
            . implode(', ', $offenders)
        );
    }

    /**
     * Parse baseline.sql into a map of table name to its column names.
     *
     * @return array<string, list<string>>
     */
    private function baselineColumns(): array
    {
        $path = dirname(__DIR__, 3) . '/db/schema/baseline.sql';
        $sql = file_get_contents($path);
        $this->assertNotFalse($sql, "Cannot read baseline at $path");

        $columns = [];
        $table = null;
        foreach (explode("\n", $sql) as $line) {
            $trimmed = trim($line);

            if ($table === null) {
                if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\(/i', $trimmed, $m)) {
                    $table = $m[1];
                    $columns[$table] = [];
                }
                continue;
            }

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            if (str_starts_with($trimmed, ')')) {
                $table = null;
                continue;
            }

            // First token of the line: a column name unless it is a key/constraint.
            $firstToken = preg_split('/\s+/', $trimmed)[0] ?? '';
            $word = strtoupper(rtrim($firstToken, ','));
            if (in_array($word, self::DDL_KEYWORDS, true)) {
                continue;
            }

            $name = trim($firstToken, "`,");
            if ($name !== '') {
                $columns[$table][] = $name;
            }
        }

        return $columns;
    }
}
