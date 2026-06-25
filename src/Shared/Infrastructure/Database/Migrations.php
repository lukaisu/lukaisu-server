<?php

/**
 * \file
 * \brief Database migrations and initialization utilities.
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

use Lukaisu\Shared\Infrastructure\ApplicationInfo;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Utilities\ErrorHandler;

/**
 * Database migrations and initialization utilities.
 *
 * Provides methods for updating database schema, running migrations,
 * and initializing the database.
 *
 * @since 3.0.0
 */
class Migrations
{
    /**
     * Drop all foreign key constraints from all tables in the database.
     *
     * This is needed before running migrations from scratch because
     * SET FOREIGN_KEY_CHECKS = 0 only affects INSERT/UPDATE/DELETE and DROP TABLE,
     * not ALTER TABLE MODIFY on columns referenced by FKs.
     *
     * @return void
     */
    public static function dropAllForeignKeys(): void
    {
        $dbname = Globals::getDatabaseName();

        // Get all foreign key constraints in the database
        $constraints = Connection::preparedFetchAll(
            "SELECT CONSTRAINT_NAME, TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
             AND TABLE_SCHEMA = ?",
            [$dbname]
        );

        foreach ($constraints as $constraint) {
            if (
                !isset($constraint['TABLE_NAME']) || !is_string($constraint['TABLE_NAME'])
                || !isset($constraint['CONSTRAINT_NAME']) || !is_string($constraint['CONSTRAINT_NAME'])
            ) {
                continue;
            }
            // Use backtick escaping for identifiers
            $escapedTable = '`' . str_replace('`', '``', $constraint['TABLE_NAME']) . '`';
            $escapedConstraint = '`' . str_replace('`', '``', $constraint['CONSTRAINT_NAME']) . '`';
            try {
                Connection::execute(
                    "ALTER TABLE $escapedTable DROP FOREIGN KEY $escapedConstraint"
                );
            } catch (\RuntimeException $e) {
                // FK might already be dropped, continue
            }
        }
    }

    /**
     * Add a prefix to table in a SQL query string.
     *
     * @param string $sql_line SQL string to prefix.
     * @param string $prefix   Prefix to add
     *
     * @return string Prefixed SQL query
     */
    public static function prefixQuery(string $sql_line, string $prefix): string
    {
        // Handle INSERT INTO (case-insensitive)
        if (strcasecmp(substr($sql_line, 0, 12), "INSERT INTO ") === 0) {
            return substr($sql_line, 0, 12) . $prefix . substr($sql_line, 12);
        }
        // Handle DROP/CREATE/ALTER TABLE with optional IF [NOT] EXISTS (case-insensitive)
        $res = preg_match(
            '/^(?:DROP|CREATE|ALTER) TABLE (?:IF (?:NOT )?EXISTS )?`?/i',
            $sql_line,
            $matches
        );
        if ($res) {
            return $matches[0] . $prefix .
            substr($sql_line, strlen($matches[0]));
        }
        return $sql_line;
    }

    /**
     * Reparse all texts in order.
     *
     * @return void
     */
    public static function reparseAllTexts(): void
    {
        // Use DELETE instead of TRUNCATE to respect foreign key constraints
        // Delete word_occurrences first (child), then sentences (parent)
        // Use raw DELETE FROM to delete all records
        Connection::execute("DELETE FROM word_occurrences");
        Connection::execute("DELETE FROM sentences");
        Maintenance::adjustAutoIncrement('sentences', 'id');
        Maintenance::initWordCount();
        // Only reparse texts that have a valid language reference
        $rows = QueryBuilder::table('texts')
            ->select(['texts.id', 'texts.language_id'])
            ->join('languages', 'texts.language_id', '=', 'languages.id')
            ->getPrepared();
        foreach ($rows as $record) {
            $id = (int) $record['id'];
            $lgId = (int) $record['language_id'];
            /** @var string|null $textValue */
            $textValue = QueryBuilder::table('texts')
                ->where('id', '=', $id)
                ->valuePrepared('text');
            TextParsing::parseAndSave(
                (string)$textValue,
                $lgId,
                $id
            );
        }
    }

    /**
     * Get list of all migration files from the migrations directory.
     *
     * @return array<string> Sorted list of migration filenames
     */
    public static function getMigrationFiles(): array
    {
        $migrationsDir = __DIR__ . '/../../../../db/migrations/';
        $files = glob($migrationsDir . '*.sql');
        if ($files === false) {
            return [];
        }
        // Extract just the filenames and sort them
        $filenames = array_map('basename', $files);
        sort($filenames);
        return $filenames;
    }

    /**
     * Get list of migrations that have already been applied.
     *
     * @return array<string> List of applied migration filenames
     */
    public static function getAppliedMigrations(): array
    {
        try {
            $rows = Connection::fetchAll("SELECT filename FROM _migrations");
            $filenames = [];
            foreach ($rows as $row) {
                if (isset($row['filename']) && is_string($row['filename'])) {
                    $filenames[] = $row['filename'];
                }
            }
            return $filenames;
        } catch (\RuntimeException $e) {
            // Table doesn't exist yet
            return [];
        }
    }

    /**
     * Record a migration as applied with its checksum.
     *
     * @param string $filename The migration filename
     * @param string $checksum SHA-256 hash of the migration file
     *
     * @return void
     */
    public static function recordMigration(string $filename, string $checksum = ''): void
    {
        Connection::preparedExecute(
            "INSERT IGNORE INTO _migrations (filename, applied_at, checksum) VALUES (?, NOW(), ?)",
            [$filename, $checksum]
        );
    }

    /**
     * Record every migration file as applied without running it.
     *
     * Used on a fresh install, where baseline.sql plus self::applyForeignKeys()
     * already produce the current schema. The historical migration chain only
     * exists to upgrade a legacy LWT database, so on a fresh install it must not
     * run: seeding it as applied keeps the bookkeeping correct and stops
     * self::update() from replaying ~60 migrations that would all fail-and-continue
     * against the already-modern schema (filling the log with noise on first boot).
     *
     * @return void
     */
    public static function markAllMigrationsApplied(): void
    {
        $migrationsDir = __DIR__ . '/../../../../db/migrations/';
        foreach (self::getMigrationFiles() as $filename) {
            $checksum = self::calculateChecksum($migrationsDir . $filename);
            self::recordMigration($filename, $checksum);
        }
    }

    /**
     * Apply the inter-table foreign keys from db/schema/foreign_keys.sql.
     *
     * baseline.sql creates every table with its user-scope FK inline but defers the
     * inter-table content FKs to that file, because a CREATE TABLE cannot reference
     * a table defined later in the same file. This is called once on a fresh install
     * (right after baseline.sql); a legacy upgrade gets the equivalent FKs from the
     * rename migrations instead, so this is not part of the per-boot rebuild. Each
     * statement is best-effort so a single failure does not abort the rest.
     *
     * @return void
     */
    public static function applyForeignKeys(): void
    {
        $queries = SqlFileParser::parseFile(__DIR__ . '/../../../../db/schema/foreign_keys.sql');
        foreach ($queries as $query) {
            try {
                Connection::execute($query);
            } catch (\Throwable $e) {
                error_log('Migrations::applyForeignKeys: ' . $e->getMessage());
            }
        }
    }

    /**
     * Calculate SHA-256 checksum for a migration file.
     *
     * @param string $filepath Full path to the migration file
     *
     * @return string SHA-256 hash or empty string if file not readable
     */
    public static function calculateChecksum(string $filepath): string
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return '';
        }
        $hash = hash_file('sha256', $filepath);
        return $hash !== false ? $hash : '';
    }

    /**
     * Validate that applied migrations haven't been modified.
     *
     * Checks the checksum of each applied migration against its stored value.
     * This detects tampering or accidental modification of migration files.
     *
     * @return array{valid: bool, errors: array<string>} Validation result
     */
    public static function validateMigrationIntegrity(): array
    {
        $errors = [];
        $migrationsDir = __DIR__ . '/../../../../db/migrations/';

        try {
            $rows = Connection::fetchAll(
                "SELECT filename, checksum FROM _migrations WHERE checksum IS NOT NULL AND checksum != ''"
            );
        } catch (\RuntimeException $e) {
            // Table doesn't exist or no checksum column yet
            return ['valid' => true, 'errors' => []];
        }

        foreach ($rows as $row) {
            $filename = $row['filename'] ?? null;
            $storedChecksum = $row['checksum'] ?? null;
            if (!is_string($filename) || !is_string($storedChecksum)) {
                continue;
            }
            $filepath = $migrationsDir . $filename;

            if (!file_exists($filepath)) {
                $errors[] = "Migration file missing: $filename";
                continue;
            }

            $currentChecksum = self::calculateChecksum($filepath);
            if ($currentChecksum !== $storedChecksum) {
                $errors[] = "Migration file integrity check failed: $filename (file was modified after being applied)";
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors
        ];
    }

    /**
     * Upgrade the _migrations table from old schema to new schema.
     *
     * Old schema stored migrations to be run; new schema tracks applied migrations.
     * This method adds the applied_at and checksum columns.
     *
     * @return void
     */
    public static function upgradeMigrationsTable(): void
    {
        // Check which columns exist
        $dbname = Globals::getDatabaseName();
        $columns = Connection::preparedFetchAll(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = '_migrations'",
            [$dbname]
        );
        $columnNames = array_column($columns, 'COLUMN_NAME');

        if (!in_array('applied_at', $columnNames)) {
            // Add applied_at column
            Connection::execute(
                "ALTER TABLE _migrations ADD COLUMN applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
            );
        }

        if (!in_array('checksum', $columnNames)) {
            // Add checksum column for integrity validation
            Connection::execute(
                "ALTER TABLE _migrations ADD COLUMN checksum VARCHAR(64) DEFAULT NULL"
            );
        }
    }

    /**
     * Whether the settings table exists in the current database.
     *
     * Used by the migration bootstrap to tell a pre-rename backup (settings present
     * but still on the legacy StKey/StValue columns, so the dbversion read throws)
     * apart from a genuinely broken install (settings missing entirely).
     *
     * @param string $dbname Current database name
     *
     * @return bool
     */
    private static function settingsTableExists(string $dbname): bool
    {
        $rows = Connection::preparedFetchAll(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'settings'",
            [$dbname]
        );
        return $rows !== [];
    }

    /**
     * Update the database if it is using an outdate version.
     *
     * @return void
     */
    public static function update(): void
    {
        $dbname = Globals::getDatabaseName();

        // DB Version
        $currversion = ApplicationInfo::getVersionNumber();

        $dbversionMissing = false;
        try {
            /** @var string|null $dbversion */
            $dbversion = QueryBuilder::table('settings')
                ->where('name', '=', 'dbversion')
                ->valuePrepared('value');
            if ($dbversion === null) {
                $dbversion = 'v001000000';
                $dbversionMissing = true;
            }
        } catch (\RuntimeException $e) {
            // The read fails when settings still carries the legacy StKey/StValue
            // columns — the state right after restoring a pre-rename backup, before
            // the settings-rename migration below has run. That is not a broken
            // database: treat it as the oldest version so every pending migration
            // (the rename included) runs and brings the schema up to date. A settings
            // table that is missing entirely is still fatal.
            if (!self::settingsTableExists($dbname)) {
                ErrorHandler::die(
                    'There is something wrong with your database ' . $dbname .
                    '. Please reinstall.'
                );
            }
            $dbversion = 'v001000000';
            $dbversionMissing = true;
        }

        // Always check for pending migrations, even if dbversion is current.
        // This handles fix migrations added after a version was released.
        $allMigrations = self::getMigrationFiles();
        $appliedMigrations = self::getAppliedMigrations();
        $pendingMigrations = array_diff($allMigrations, $appliedMigrations);

        // Do DB Updates if tables seem to be old versions
        $needsVersionUpdate = $dbversion < $currversion;

        if ($needsVersionUpdate) {
            if (
                'utf8utf8_general_ci' != Connection::preparedFetchValue(
                    'SELECT concat(default_character_set_name, default_collation_name) AS collation
                FROM information_schema.SCHEMATA
                WHERE schema_name = ?',
                    [$dbname],
                    'collation'
                )
            ) {
                Connection::query("SET collation_connection = 'utf8_general_ci'");
                // Note: ALTER DATABASE doesn't support prepared statements
                // Database name comes from trusted config, using backtick escaping
                $escapedDbName = '`' . str_replace('`', '``', $dbname) . '`';
                Connection::execute(
                    'ALTER DATABASE ' . $escapedDbName .
                    ' CHARACTER SET utf8 COLLATE utf8_general_ci'
                );
            }
        }

        if (count($pendingMigrations) > 0) {
            // Validate integrity of already-applied migrations
            $integrityCheck = self::validateMigrationIntegrity();
            if (!$integrityCheck['valid']) {
                // Log errors but don't block - allow admin to investigate
                foreach ($integrityCheck['errors'] as $error) {
                    error_log('Migration integrity warning: ' . $error);
                }
            }

            $migrationsDir = __DIR__ . '/../../../../db/migrations/';

            // Drop all FK constraints before running migrations.
            // SET FOREIGN_KEY_CHECKS = 0 only affects INSERT/UPDATE/DELETE and DROP TABLE,
            // not ALTER TABLE MODIFY on columns referenced by FKs.
            // The migrations will recreate FKs as needed.
            self::dropAllForeignKeys();

            // Disable FK checks during migrations to handle legacy data
            // that may not satisfy new FK constraints until fully migrated
            Connection::execute("SET FOREIGN_KEY_CHECKS = 0");
            try {
                foreach ($pendingMigrations as $filename) {
                    $filepath = $migrationsDir . $filename;
                    $queries = SqlFileParser::parseFile($filepath);
                    foreach ($queries as $sql_query) {
                        try {
                            Connection::execute($sql_query);
                        } catch (\RuntimeException $e) {
                            // Log per-statement failure but continue with remaining
                            // statements. This handles fresh installs where baseline
                            // creates modern tables and legacy migrations reference
                            // old table names that no longer exist.
                            error_log("Migration failed: $filename - " . $e->getMessage());
                        }
                    }
                    // Always record the migration so it won't be retried on every
                    // request. Failed statements are logged for investigation.
                    $checksum = self::calculateChecksum($filepath);
                    self::recordMigration($filename, $checksum);
                }
            } finally {
                Connection::execute("SET FOREIGN_KEY_CHECKS = 1");
            }
        }

        if ($needsVersionUpdate) {
            Connection::execute(
                "CREATE TABLE IF NOT EXISTS tts (
                    TtsID mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                    TtsTxt varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
                    TtsLc varchar(8) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
                    PRIMARY KEY (TtsID),
                    UNIQUE KEY TtsTxtLC (TtsTxt,TtsLc)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 PACK_KEYS=1"
            );

            // Set database to current version
            Settings::save('dbversion', $currversion);
            Settings::save('lastscorecalc', '0');  // Reset to trigger recalculation
        } elseif ($dbversionMissing) {
            // First run on a database whose schema already matches the current
            // version (e.g. a fresh install from baseline): record the version
            // marker so it exists, without the version-upgrade side effects.
            Settings::save('dbversion', $currversion);
        }
    }

    /**
     * Check and/or update the database.
     *
     * @return void
     */
    public static function checkAndUpdate(): void
    {
        $tables = array();

        // Get database name for INFORMATION_SCHEMA query
        $dbname = Globals::getDatabaseName();

        // Get all core Lukaisu Server tables (no prefix in multi-user system)
        $res = Connection::preparedFetchAll(
            "SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ?
             AND TABLE_NAME IN (
                'languages', 'texts', 'words', 'sentences',
                'word_occurrences', 'tags', 'text_tags', 'word_tag_map',
                'text_tag_map', 'news_feeds', 'feed_links',
                'settings', '_migrations'
             )",
            [$dbname]
        );
        foreach ($res as $row) {
            $tables[] = (string) $row['TABLE_NAME'];
        }

        // A fresh install is an empty database: none of the core tables exist yet,
        // captured here BEFORE baseline.sql runs below. In that case the historical
        // migration chain (which only upgrades a legacy LWT/Hungarian schema) has
        // nothing to do, so the fresh-install branch further down seeds it as
        // already-applied instead of replaying every migration. A legacy or restored
        // database already has its tables, so it is not "fresh" and the migrations
        // still run to transform it.
        $isFreshInstall = ($tables === []);

        /// counter for cache rebuild
        $count = 0;

        // Rebuild in missing table (best-effort).
        // On a legacy or restored schema a brand-new baseline table can carry an
        // FK to a column that a pending rename migration has not produced yet
        // (e.g. review_log -> words.id before the words-rename migration runs),
        // which makes its CREATE fail with errno 150. Skip such a statement and
        // let self::update() below create the table once the migrations have
        // renamed the referenced columns. On a fresh install every statement
        // succeeds, so this only affects legacy upgrades/restores.
        $queries = SqlFileParser::parseFile(__DIR__ . "/../../../../db/schema/baseline.sql");
        foreach ($queries as $query) {
            // Execute schema queries directly - no prefix in multi-user system
            try {
                $count += (int) Connection::execute($query);
            } catch (\Throwable $e) {
                error_log(
                    'Migrations::checkAndUpdate: skipping baseline statement during '
                    . 'rebuild (a pending migration will create it): ' . $e->getMessage()
                );
            }
        }

        // Ensure _migrations table has the new schema with applied_at column
        self::upgradeMigrationsTable();

        // Fresh install: baseline.sql just created the complete modern schema.
        // Apply the inter-table foreign keys (baseline defers them so they can be
        // added once every table exists) and record every migration as applied so
        // update() skips the legacy chain entirely. This keeps first boot quiet
        // instead of logging ~60 expected "Migration failed" lines.
        if ($isFreshInstall) {
            self::applyForeignKeys();
            self::markAllMigrationsApplied();
        }

        // Update the database (if necessary)
        self::update();

        if (!in_array("word_occurrences", $tables) && !in_array("word_occurrences", $tables)) {
            // Add data from the old database system
            if (in_array("textitems", $tables)) {
                // Complex migration query - use raw SQL
                Connection::execute(
                    "INSERT INTO word_occurrences (
                        word_id, language_id, text_id, sentence_id, position, word_count,
                        text
                    )
                    SELECT IFNULL(id,0), TiLgID, TiTxID, sentence_id, position,
                    CASE WHEN TiIsNotWord = 1 THEN 0 ELSE word_count END as WordCount,
                    CASE
                        WHEN STRCMP(text COLLATE utf8_bin,TiTextLC)!=0 OR word_count=1
                        THEN text
                        ELSE ''
                    END AS Text
                    FROM textitems
                    LEFT JOIN words ON TiTextLC=text_lc AND TiLgID=language_id
                    WHERE word_count<2 OR id IS NOT NULL"
                );
                QueryBuilder::table('textitems')->truncate();
            }
            $count++;
        }

        if ($count > 0) {
            // Rebuild Text Cache if cache tables new
            self::reparseAllTexts();
        }


        // Daily housekeeping: clean orphaned tag maps and optimize the database.
        // FSRS scheduling (issue #238) needs no daily recompute — `due_at` is an
        // absolute date, not a decaying cache like the retired Leitner scores.
        $lastscorecalc = Settings::get('lastscorecalc');
        $today = date('Y-m-d');
        if ($lastscorecalc != $today) {
            // Clean up orphaned word_tag_map (tags deleted)
            Connection::execute(
                "DELETE word_tag_map
                FROM (word_tag_map LEFT JOIN tags on tag_id = id)
                WHERE id IS NULL"
            );
            // Clean up orphaned word_tag_map (words deleted)
            Connection::execute(
                "DELETE word_tag_map
                FROM (word_tag_map LEFT JOIN words ON word_id = id)
                WHERE id IS NULL"
            );
            // Clean up orphaned text_tag_map (text_tags deleted)
            Connection::execute(
                "DELETE text_tag_map
                FROM (text_tag_map LEFT JOIN text_tags ON text_tag_id = id)
                WHERE id IS NULL"
            );
            // Clean up orphaned text_tag_map (texts deleted)
            Connection::execute(
                "DELETE text_tag_map
                FROM (text_tag_map LEFT JOIN texts ON text_id = id)
                WHERE id IS NULL"
            );
            Maintenance::optimizeDatabase();
            Settings::save('lastscorecalc', $today);
        }
    }
}
