<?php

/**
 * Complete Import Service - Complex import with temp tables, translation merge, tags
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\Services;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\DB;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Tags\Application\TagsFacade;

/**
 * Handles complete term import with temp tables, translation merging,
 * overwrite modes, and tag processing.
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class CompleteImportService
{
    private ImportUtilities $utilities;

    /**
     * @param ImportUtilities $utilities Shared import helpers
     */
    public function __construct(ImportUtilities $utilities)
    {
        $this->utilities = $utilities;
    }

    /**
     * Import terms with complete processing (handles tags, overwrite modes).
     *
     * @param int    $langId        Language ID
     * @param array{txt: int, tr: int, ro: int, se: int, tl: int} $fields Field indexes
     * @param string $columnsClause SQL columns clause
     * @param string $delimiter     Field delimiter
     * @param string $fileName      Path to input file
     * @param int    $status        Word status
     * @param int    $overwrite     Overwrite mode
     * @param bool   $ignoreFirst   Ignore first line
     * @param string $translDelim   Translation delimiter
     * @param string $tabType       Tab type (c, t, h)
     *
     * @return void
     */
    public function importComplete(
        int $langId,
        array $fields,
        string $columnsClause,
        string $delimiter,
        string $fileName,
        int $status,
        int $overwrite,
        bool $ignoreFirst,
        string $translDelim,
        string $tabType
    ): void {
        $removeSpaces = (bool) QueryBuilder::table('languages')
            ->where('LgID', '=', $langId)
            ->valuePrepared('LgRemoveSpaces');

        DB::beginTransaction();
        try {
            $this->initTempTables();

            if ($this->utilities->isLocalInfileEnabled()) {
                $this->loadDataToTempTable(
                    $removeSpaces,
                    $fields,
                    $columnsClause,
                    $delimiter,
                    $fileName,
                    $ignoreFirst
                );
            } else {
                $this->loadDataToTempTableWithPHP(
                    $removeSpaces,
                    $fields,
                    $delimiter,
                    $fileName,
                    $ignoreFirst
                );
            }

            // Handle translation merging for overwrite modes 4 and 5
            if ($overwrite > 3) {
                $this->handleTranslationMerge($langId, $translDelim, $tabType);
            }

            // Execute the main import/update query
            $this->executeMainImportQuery($langId, $fields, $status, $overwrite);

            // Handle tags if tag list field is specified
            if ($fields["tl"] != 0) {
                $this->handleTagsImport($langId);
            }

            $this->cleanupTempTables();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            $this->cleanupTempTables();
            throw $e;
        }
    }

    /**
     * Initialize temporary tables for import.
     *
     * @return void
     */
    private function initTempTables(): void
    {
        Connection::execute(
            "CREATE TEMPORARY TABLE IF NOT EXISTS numbers(
                n tinyint(3) unsigned NOT NULL
            )"
        );
        Connection::execute(
            "INSERT IGNORE INTO numbers(n) VALUES ('1'),('2'),('3'),
            ('4'),('5'),('6'),('7'),('8'),('9')"
        );
    }

    /**
     * Load data into temporary table using LOAD DATA.
     *
     * @param bool   $removeSpaces  Whether to remove spaces
     * @param array  $fields        Field indexes
     * @param string $columnsClause SQL columns clause
     * @param string $delimiter     Field delimiter
     * @param string $fileName      Path to input file
     * @param bool   $ignoreFirst   Ignore first line
     *
     * @return void
     */
    private function loadDataToTempTable(
        bool $removeSpaces,
        array $fields,
        string $columnsClause,
        string $delimiter,
        string $fileName,
        bool $ignoreFirst
    ): void {
        // MariaDB / MySQL do not accept `?` placeholders for the filename
        // in LOAD DATA INFILE — the filename is parsed at execution time,
        // not at prepare. Inline the path with proper escaping. The path
        // is server-controlled (either the PHP-generated `tmp_name` of an
        // upload, or the `createTempFile()` output for pasted text), so
        // there is no user input in this string.
        $escapedFileName = Connection::escape($fileName);

        $sql = "LOAD DATA LOCAL INFILE '$escapedFileName'
            INTO TABLE temp_words
            FIELDS TERMINATED BY '$delimiter' ENCLOSED BY '\"' LINES TERMINATED BY '\\n' " .
            ($ignoreFirst ? "IGNORE 1 LINES " : "") .
            "$columnsClause SET " .
            ($removeSpaces ?
                'text_lc = LOWER(REPLACE(@wotext," ","")), text = REPLACE(@wotext," ","")' :
                'text_lc = LOWER(text)');

        if ($fields["tl"] != 0) {
            $sql .= ', tag_list = REPLACE(@taglist, " ", ",")';
        }

        Connection::execute($sql);
    }

    /**
     * Load data into temporary table using PHP (fallback).
     * Uses chunked batch inserts to handle large files without excessive memory.
     *
     * @param bool   $removeSpaces Whether to remove spaces
     * @param array{txt: int, tr: int, ro: int, se: int, tl: int} $fields Field indexes
     * @param string $delimiter    Field delimiter
     * @param string $fileName     Path to input file
     * @param bool   $ignoreFirst  Ignore first line
     *
     * @return void
     */
    private function loadDataToTempTableWithPHP(
        bool $removeSpaces,
        array $fields,
        string $delimiter,
        string $fileName,
        bool $ignoreFirst
    ): void {
        if ($delimiter === '') {
            return;
        }
        $handle = fopen($fileName, 'r');
        if ($handle === false) {
            return;
        }

        /** @var list<list<string>> $rows */
        $rows = [];
        $lineNum = 0;

        while (($line = fgets($handle)) !== false) {
            if ($lineNum++ == 0 && $ignoreFirst) {
                continue;
            }

            $line = rtrim($line, "\r\n");
            if (empty(trim($line))) {
                continue;
            }

            /** @var list<string> $parsedLine */
            $parsedLine = explode($delimiter, $line);

            $txtIdx = $fields["txt"] - 1;
            if (!isset($parsedLine[$txtIdx])) {
                continue;
            }

            $wotext = $parsedLine[$txtIdx];

            /** @var list<string> $row */
            $row = [];
            // Fill text and text_lc
            if ($removeSpaces) {
                $row[] = str_replace(" ", "", $wotext);
                $row[] = mb_strtolower(str_replace(" ", "", $wotext));
            } else {
                $row[] = $wotext;
                $row[] = mb_strtolower($wotext);
            }

            $trIdx = $fields["tr"] - 1;
            $roIdx = $fields["ro"] - 1;
            $seIdx = $fields["se"] - 1;
            $tlIdx = $fields["tl"] - 1;

            if ($fields["tr"] != 0 && isset($parsedLine[$trIdx])) {
                $row[] = $parsedLine[$trIdx];
            }
            if ($fields["ro"] != 0 && isset($parsedLine[$roIdx])) {
                $row[] = $parsedLine[$roIdx];
            }
            if ($fields["se"] != 0 && isset($parsedLine[$seIdx])) {
                $row[] = $parsedLine[$seIdx];
            }
            if ($fields["tl"] != 0 && isset($parsedLine[$tlIdx])) {
                $row[] = str_replace(" ", ",", $parsedLine[$tlIdx]);
            }

            $rows[] = $row;

            // Execute batch when we reach the batch size
            if (count($rows) >= ImportUtilities::BATCH_SIZE) {
                $this->executeTempTableBatch($rows, $fields);
                $rows = [];
            }
        }

        fclose($handle);

        // Execute remaining rows
        if (!empty($rows)) {
            $this->executeTempTableBatch($rows, $fields);
        }
    }

    /**
     * Execute a batch insert for temp table import.
     *
     * @param list<list<string>> $rows   Array of row data
     * @param array{txt: int, tr: int, ro: int, se: int, tl: int} $fields Field indexes
     *
     * @return void
     */
    private function executeTempTableBatch(array $rows, array $fields): void
    {
        if (empty($rows)) {
            return;
        }

        // Build placeholder string for one row
        $rowPlaceholders = '(?, ?';  // text, text_lc
        if ($fields["tr"] != 0) {
            $rowPlaceholders .= ', ?';
        }
        if ($fields["ro"] != 0) {
            $rowPlaceholders .= ', ?';
        }
        if ($fields["se"] != 0) {
            $rowPlaceholders .= ', ?';
        }
        if ($fields["tl"] != 0) {
            $rowPlaceholders .= ', ?';
        }
        $rowPlaceholders .= ')';

        $placeholders = array_fill(0, count($rows), $rowPlaceholders);
        /** @var list<string> $params */
        $params = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }

        $sql = "INSERT INTO temp_words(
                text, text_lc" .
                ($fields["tr"] != 0 ? ', translation' : '') .
                ($fields["ro"] != 0 ? ', romanization' : '') .
                ($fields["se"] != 0 ? ', sentence' : '') .
                ($fields["tl"] != 0 ? ", tag_list" : "") .
            ")
            VALUES " . implode(',', $placeholders);

        Connection::preparedExecute($sql, $params);
    }

    /**
     * Handle translation merging for overwrite modes 4 and 5.
     *
     * @param int    $langId      Language ID
     * @param string $translDelim Translation delimiter from import
     * @param string $tabType     Tab type (c, t, h)
     *
     * @return void
     */
    private function handleTranslationMerge(int $langId, string $translDelim, string $tabType): void
    {
        Connection::execute(
            "CREATE TEMPORARY TABLE IF NOT EXISTS merge_words(
                MID mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                MText varchar(250) NOT NULL,
                MTranslation varchar(250) NOT NULL,
                PRIMARY KEY (MID),
                UNIQUE KEY (MText, MTranslation)
            ) DEFAULT CHARSET=utf8"
        );

        $wosep = Settings::getWithDefault('set-term-translation-delimiters');
        if (empty($wosep)) {
            $wosep = match ($tabType) {
                'h' => '#',
                'c' => ',',
                default => "\t",
            };
        }

        $seplen = mb_strlen($wosep, 'UTF-8');
        $woTrRepl = 'words.translation';
        $replaceParams = [];
        for ($i = 1; $i < $seplen; $i++) {
            $woTrRepl = 'REPLACE(' . $woTrRepl . ', ?, ?)';
            $replaceParams[] = $wosep[$i];
            $replaceParams[] = $wosep[0];
        }

        // Insert existing translations. The user-scope clause goes inside the
        // inner subquery's join condition so its placeholder sits between
        // `language_id = ?` and the trailing CHAR_LENGTH `?`.
        $bindings = array_merge(
            $replaceParams,
            [$wosep[0], $wosep[0], $langId]
        );
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
        $bindings[] = $wosep[0];

        $sql = "INSERT IGNORE INTO merge_words(MText,MTranslation)
            SELECT b.text_lc,
            trim(
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX(b.translation, ?, numbers.n),
                    ?, -1
                )
            ) name
            FROM numbers
            INNER JOIN (
                SELECT words.text_lc as text_lc, $woTrRepl as translation
                FROM temp_words
                LEFT JOIN words
                ON words.text_lc = temp_words.text_lc
                    AND words.translation != '*'
                    AND words.language_id = ?{$userScope}
            ) b
            ON CHAR_LENGTH(b.translation)-CHAR_LENGTH(REPLACE(b.translation, ?, ''))>= numbers.n-1
            ORDER BY b.text_lc, n";

        $stmt = Connection::prepare($sql);
        $stmt->bindValues($bindings);
        $stmt->execute();

        // Handle import delimiter
        $tesep = $translDelim;
        if (empty($tesep)) {
            $tesep = match ($tabType) {
                'h' => '#',
                'c' => ',',
                default => "\t",
            };
        }

        $seplen = mb_strlen($tesep, 'UTF-8');
        $woTrRepl = 'temp_words.translation';
        $replaceParams2 = [];
        for ($i = 1; $i < $seplen; $i++) {
            $woTrRepl = 'REPLACE(' . $woTrRepl . ', ?, ?)';
            $replaceParams2[] = $tesep[$i];
            $replaceParams2[] = $tesep[0];
        }

        // Insert new translations
        $params2 = array_merge(
            $replaceParams2,
            [$tesep[0], $tesep[0], $tesep[0]]
        );

        $stmt = Connection::prepare(
            "INSERT IGNORE INTO merge_words(MText,MTranslation)
            SELECT temp_words.text_lc,
            trim(
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX($woTrRepl, ?,
                        numbers.n
                    ), ?, -1
                )
            ) name
            FROM numbers
            INNER JOIN temp_words
            ON CHAR_LENGTH(temp_words.translation)-CHAR_LENGTH(REPLACE($woTrRepl, ?, ''))>= numbers.n-1
            ORDER BY temp_words.text_lc, n"
        );
        $stmt->bindValues($params2);
        $stmt->execute();

        // Determine separator for output
        if ($wosep[0] == ',' || $wosep[0] == ';') {
            $wosep = $wosep[0] . ' ';
        } else {
            $wosep = ' ' . $wosep[0] . ' ';
        }

        // Update temp_words with merged translations
        Connection::preparedExecute(
            "UPDATE temp_words
            LEFT JOIN (
                SELECT MText, GROUP_CONCAT(trim(MTranslation)
                    ORDER BY MID
                    SEPARATOR ?
                ) AS Translation
                FROM merge_words
                GROUP BY MText
            ) A
            ON MText=text_lc
            SET translation = Translation",
            [$wosep]
        );

        Connection::execute("DROP TABLE merge_words");
    }

    /**
     * Execute the main import/update query based on overwrite mode.
     *
     * @param int   $langId    Language ID
     * @param array $fields    Field indexes
     * @param int   $status    Word status
     * @param int   $overwrite Overwrite mode (0-5)
     *
     * @return void
     */
    private function executeMainImportQuery(int $langId, array $fields, int $status, int $overwrite): void
    {
        if ($overwrite != 3 && $overwrite != 5) {
            // Stamp user_id on every imported row. Without this, multi-user
            // installs silently lost imports: rows landed with user_id NULL
            // and then never showed up in the caller's vocab list (the
            // QueryBuilder filter `user_id = currentUserId` excluded them).
            $userScopeCol = UserScopedQuery::insertColumn('words');
            $userScopeVal = UserScopedQuery::insertValue('words');

            $sql = "INSERT " . ($overwrite != 0 ? '' : 'IGNORE ') .
                " INTO words (
                    text_lc, text, translation, romanization, sentence,
                    status, status_changed_at, language_id{$userScopeCol},
                    " . TermStatusService::makeScoreRandomInsertUpdate('iv') . "
                )
                SELECT *, $langId as LgID{$userScopeVal}, " . TermStatusService::makeScoreRandomInsertUpdate('id') . "
                FROM (
                    SELECT text_lc, text, translation, romanization,
                    sentence, $status AS status,
                    NOW() AS status_changed_at
                    FROM temp_words
                ) AS tw";

            if ($overwrite == 1 || $overwrite == 4) {
                $sql .= " ON DUPLICATE KEY UPDATE " .
                    ($fields["tr"] ? "words.translation = tw.translation, " : "") .
                    ($fields["ro"] ? "words.romanization = tw.romanization, " : '') .
                    ($fields["se"] ? "words.sentence = tw.sentence, " : '') .
                    "words.status = tw.status,
                    words.status_changed_at = tw.status_changed_at";
            }

            if ($overwrite == 2) {
                $sql .= " ON DUPLICATE KEY UPDATE
                    words.translation = CASE
                        WHEN words.translation = \"*\" THEN tw.translation
                        ELSE words.translation
                    END,
                    words.romanization = CASE
                        WHEN words.romanization IS NULL THEN tw.romanization
                        ELSE words.romanization
                    END,
                    words.sentence = CASE
                        WHEN words.sentence IS NULL THEN tw.sentence
                        ELSE words.sentence
                    END,
                    words.status_changed_at = CASE
                        WHEN words.sentence IS NULL OR words.romanization IS NULL OR words.translation = \"*\"
                        THEN tw.status_changed_at
                        ELSE words.status_changed_at
                    END";
            }

            Connection::execute($sql);
            return;
        }

        // Overwrite modes 3 and 5: only update existing, don't insert new.
        // forTablePrepared appends ` AND a.user_id = ?` and pushes the
        // current user id into $bindings; switching the call from
        // `Connection::execute` to `preparedExecute` is what binds it.
        // Pre-fix the `?` was unbound and the statement either failed
        // outright or (in single-user mode) silently ran without scope.
        $bindings = [];
        $sql = "UPDATE words AS a
            JOIN temp_words AS b
            ON a.text_lc = b.text_lc SET
            a.translation = CASE
                WHEN b.translation = '' OR b.translation = '*' THEN a.translation
                ELSE b.translation
            END,
            a.romanization = CASE
                WHEN b.romanization IS NULL OR b.romanization = '' THEN a.romanization
                ELSE b.romanization
            END,
            a.sentence = CASE
                WHEN b.sentence IS NULL OR b.sentence = '' THEN a.sentence
                ELSE b.sentence
            END,
            a.status_changed_at = CASE
                WHEN (b.translation = '' OR b.translation = '*')
                    AND (b.romanization IS NULL OR b.romanization = '')
                    AND (b.sentence IS NULL OR b.sentence = '')
                THEN a.status_changed_at
                ELSE NOW()
            END"
            . UserScopedQuery::forTablePrepared('words', $bindings, 'a');

        Connection::preparedExecute($sql, $bindings);
    }

    /**
     * Handle tags import.
     *
     * @param int $langId Language ID
     *
     * @return void
     */
    private function handleTagsImport(int $langId): void
    {
        // Insert new tags. Stamp user_id so the new rows belong to the
        // caller — pre-fix they landed with user_id NULL and were
        // invisible to every user (and to the per-user composite
        // unique on (user_id, text) introduced in
        // 20260503_120000_per_user_name_uniques.sql, NULL is treated
        // as distinct, so duplicates accumulated silently).
        $tagInsertCol = UserScopedQuery::insertColumn('tags');
        $tagInsertVal = UserScopedQuery::insertValue('tags');
        Connection::execute(
            "INSERT IGNORE INTO tags (text{$tagInsertCol})
            SELECT name{$tagInsertVal} FROM (
                SELECT temp_words.text_lc,
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX(
                        temp_words.tag_list, ',',
                        numbers.n
                    ), ',', -1) name
                FROM numbers
                INNER JOIN temp_words
                ON CHAR_LENGTH(temp_words.tag_list)-CHAR_LENGTH(REPLACE(temp_words.tag_list, ',', ''))>= numbers.n-1
                ORDER BY text_lc, n) A"
        );

        // Link words to tags. Scope BOTH `words` and `tags` to the
        // caller — without the tags filter the join `name = text`
        // could match a foreign user's tag with the same text and
        // attach it to the caller's words, polluting the foreign
        // user's tag-to-word membership (the same shape as F13's
        // getOrCreateTermTag fix).
        $bindings = [$langId];
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings)
            . UserScopedQuery::forTablePrepared('tags', $bindings);
        $sql = "INSERT IGNORE INTO word_tag_map
            SELECT id, id
            FROM (
                SELECT temp_words.text_lc, SUBSTRING_INDEX(
                    SUBSTRING_INDEX(
                        temp_words.tag_list, ',', numbers.n
                    ), ',', -1) name
                FROM numbers
                INNER JOIN temp_words
                ON CHAR_LENGTH(temp_words.tag_list)-CHAR_LENGTH(REPLACE(temp_words.tag_list, ',', ''))>= numbers.n-1
                ORDER BY text_lc, n
            ) A, tags, words
            WHERE name=text AND A.text_lc=words.text_lc AND language_id=?"
            . $userScope;

        Connection::preparedExecute($sql, $bindings);

        TagsFacade::getAllTermTags(true);
    }

    /**
     * Cleanup temporary tables.
     *
     * @return void
     */
    private function cleanupTempTables(): void
    {
        Connection::execute("DROP TABLE IF EXISTS numbers");
        QueryBuilder::table('temp_words')->truncate();
    }

    /**
     * Import tags only (no terms).
     *
     * @param array{tl: int} $fields      Field indexes
     * @param string         $tabType     Tab type (c, t, h)
     * @param string         $fileName    Path to input file
     * @param bool           $ignoreFirst Ignore first line
     *
     * @return void
     */
    public function importTagsOnly(array $fields, string $tabType, string $fileName, bool $ignoreFirst): void
    {
        $columns = '';
        $tlField = $fields["tl"];
        for ($j = 1; $j <= $tlField; $j++) {
            $columns .= ($j == 1 ? '(' : ',') . ($j == $fields["tl"] ? '@taglist' : '@dummy');
        }
        $columns .= ')';

        $delimiter = ' ' . $this->utilities->getSqlDelimiter($tabType);

        if ($this->utilities->isLocalInfileEnabled()) {
            // See loadDataToTempTable() — `?` is not accepted for LOAD
            // DATA's filename, inline with proper escaping. The path is
            // server-controlled.
            $escapedFileName = Connection::escape($fileName);
            $sql = "LOAD DATA LOCAL INFILE '$escapedFileName'
                IGNORE INTO TABLE temp_words
                FIELDS TERMINATED BY '$delimiter' ENCLOSED BY '\"' LINES TERMINATED BY '\\n' " .
                ($ignoreFirst ? "IGNORE 1 LINES " : "") .
                "$columns
                SET text_lc = REPLACE(@taglist, ' ', ',')";
            Connection::execute($sql);
        } else {
            $handle = fopen($fileName, 'r');
            if ($handle === false) {
                return;
            }
            $fileSize = filesize($fileName);
            if ($fileSize === false || $fileSize === 0) {
                fclose($handle);
                return;
            }
            $dataText = fread($handle, $fileSize);
            fclose($handle);
            if ($dataText === false) {
                return;
            }

            $params = [];
            $placeholders = [];
            $i = 0;
            $realDelimiter = $this->utilities->getDelimiter($tabType);
            if ($realDelimiter === '') {
                return;
            }

            foreach (explode(PHP_EOL, $dataText) as $line) {
                if ($i++ == 0 && $ignoreFirst) {
                    continue;
                }

                if (empty(trim($line))) {
                    continue;
                }

                /** @var list<string> $parts */
                $parts = explode($realDelimiter, $line);
                $tlIdx = $tlField - 1;
                if (!isset($parts[$tlIdx])) {
                    continue;
                }

                $tags = $parts[$tlIdx];
                $tags = str_replace(' ', ',', $tags);
                $params[] = $tags;
                $placeholders[] = "(?)";
            }

            if (!empty($placeholders)) {
                $sql = "INSERT INTO temp_words(text_lc)
                    VALUES " . implode(',', $placeholders);
                Connection::preparedExecute($sql, $params);
            }
        }

        // Create numbers table and insert tags
        Connection::execute(
            "CREATE TEMPORARY TABLE IF NOT EXISTS numbers(
                n tinyint(3) unsigned NOT NULL
            )"
        );
        Connection::execute("INSERT IGNORE INTO numbers(n) VALUES ('1'),('2'),('3'),
            ('4'),('5'),('6'),('7'),('8'),('9')");

        // Stamp user_id so the imported tags are owned by the caller —
        // see handleTagsImport() for context. Without this the rows
        // land with user_id NULL and never appear on the user's tag
        // page.
        $tagInsertCol = UserScopedQuery::insertColumn('tags');
        $tagInsertVal = UserScopedQuery::insertValue('tags');
        Connection::execute("INSERT IGNORE INTO tags (text{$tagInsertCol})
            SELECT NAME{$tagInsertVal} FROM (
                SELECT SUBSTRING_INDEX(
                    SUBSTRING_INDEX(
                        temp_words.text_lc, ',', numbers.n
                    ), ',', -1) name
                FROM numbers
                INNER JOIN temp_words
                ON CHAR_LENGTH(temp_words.text_lc)-CHAR_LENGTH(REPLACE(temp_words.text_lc, ',', ''))>= numbers.n-1
                ORDER BY text_lc, n) A");

        $this->cleanupTempTables();
        TagsFacade::getAllTermTags(true);
    }
}
