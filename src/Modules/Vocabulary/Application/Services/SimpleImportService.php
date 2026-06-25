<?php

/**
 * Simple Import Service - Import terms using LOAD DATA or PHP fallback
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
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Handles simple term import (no tags, no overwrite).
 *
 * Supports two import strategies: LOAD DATA LOCAL INFILE (fast, requires
 * server/client support) and PHP-based line-by-line parsing (universal fallback).
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class SimpleImportService
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
     * Import terms using simple import (no tags, no overwrite).
     *
     * @param int    $langId        Language ID
     * @param array{txt: int, tr: int, ro: int, se: int, tl?: int} $fields Field indexes
     * @param string $columnsClause SQL columns clause
     * @param string $delimiter     Field delimiter
     * @param string $fileName      Path to input file
     * @param int    $status        Word status
     * @param bool   $ignoreFirst   Ignore first line
     *
     * @return void
     */
    public function importSimple(
        int $langId,
        array $fields,
        string $columnsClause,
        string $delimiter,
        string $fileName,
        int $status,
        bool $ignoreFirst
    ): void {
        $removeSpaces = (bool) QueryBuilder::table('languages')
            ->where('id', '=', $langId)
            ->valuePrepared('remove_spaces');

        if ($this->utilities->isLocalInfileEnabled()) {
            $this->importSimpleWithLoadData(
                $langId,
                $removeSpaces,
                $columnsClause,
                $delimiter,
                $fileName,
                $status,
                $ignoreFirst
            );
        } else {
            $this->importSimpleWithPHP(
                $langId,
                $fields,
                $removeSpaces,
                $delimiter,
                $fileName,
                $status,
                $ignoreFirst
            );
        }
    }

    /**
     * Import terms using LOAD DATA LOCAL INFILE.
     *
     * @param int    $langId        Language ID
     * @param bool   $removeSpaces  Whether to remove spaces
     * @param string $columnsClause SQL columns clause
     * @param string $delimiter     Field delimiter
     * @param string $fileName      Path to input file
     * @param int    $status        Word status
     * @param bool   $ignoreFirst   Ignore first line
     *
     * @return void
     */
    private function importSimpleWithLoadData(
        int $langId,
        bool $removeSpaces,
        string $columnsClause,
        string $delimiter,
        string $fileName,
        int $status,
        bool $ignoreFirst
    ): void {
        $bindings = [$fileName, $langId, $status];
        $sql = "LOAD DATA LOCAL INFILE ?
            IGNORE INTO TABLE words
            FIELDS TERMINATED BY '$delimiter' ENCLOSED BY '\"' LINES TERMINATED BY '\\n' " .
            ($ignoreFirst ? "IGNORE 1 LINES " : "") .
            "$columnsClause
            SET language_id = ?, " .
            ($removeSpaces ?
                'text_lc = LOWER(REPLACE(@wotext," ","")), text = REPLACE(@wotext, " ", "")' :
                'text_lc = LOWER(text)') . ",
            status = ?, status_changed_at = NOW(), " .
            TermStatusService::makeScoreRandomInsertUpdate('u');

        $stmt = Connection::prepare($sql);
        $stmt->bind('sis', $fileName, $langId, $status);
        $stmt->execute();
    }

    /**
     * Import terms using PHP parsing (fallback when LOAD DATA not available).
     * Uses chunked batch inserts to handle large files without excessive memory.
     *
     * @param int                                  $langId       Language ID
     * @param array{txt: int, tr: int, ro: int, se: int, tl?: int} $fields Field indexes
     * @param bool                                 $removeSpaces Whether to remove spaces
     * @param string                               $delimiter    Field delimiter
     * @param string                               $fileName     Path to input file
     * @param int                                  $status       Word status
     * @param bool                                 $ignoreFirst  Ignore first line
     *
     * @return void
     */
    private function importSimpleWithPHP(
        int $langId,
        array $fields,
        bool $removeSpaces,
        string $delimiter,
        string $fileName,
        int $status,
        bool $ignoreFirst
    ): void {
        if ($delimiter === '') {
            return;
        }
        $handle = fopen($fileName, 'r');
        if ($handle === false) {
            return;
        }

        /** @var list<list<int|string>> $rows */
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

            /** @var list<int|string> $row */
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

            if ($fields["tr"] != 0 && isset($parsedLine[$trIdx])) {
                $row[] = $parsedLine[$trIdx];
            }
            if ($fields["ro"] != 0 && isset($parsedLine[$roIdx])) {
                $row[] = $parsedLine[$roIdx];
            }
            if ($fields["se"] != 0 && isset($parsedLine[$seIdx])) {
                $row[] = $parsedLine[$seIdx];
            }

            $row[] = $langId;
            $row[] = $status;

            $rows[] = $row;

            // Execute batch when we reach the batch size
            if (count($rows) >= ImportUtilities::BATCH_SIZE) {
                $this->executeSimpleImportBatch($rows, $fields);
                $rows = [];
            }
        }

        fclose($handle);

        // Execute remaining rows
        if (!empty($rows)) {
            $this->executeSimpleImportBatch($rows, $fields);
        }
    }

    /**
     * Execute a batch insert for simple import.
     *
     * @param list<list<int|string>> $rows   Array of row data
     * @param array{txt: int, tr: int, ro: int, se: int, tl?: int} $fields Field indexes
     *
     * @return void
     */
    private function executeSimpleImportBatch(array $rows, array $fields): void
    {
        if (empty($rows)) {
            return;
        }

        $userId = UserScopedQuery::getUserIdForInsert('words');

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
        // language_id, status, status_changed_at. FSRS columns default to a new
        // card due now (issue #238); no legacy score columns.
        $rowPlaceholders .= ', ?, ?, NOW()';

        if ($userId !== null) {
            $rowPlaceholders .= ', ?';
        }
        $rowPlaceholders .= ')';

        $placeholders = array_fill(0, count($rows), $rowPlaceholders);
        /** @var list<int|string> $params */
        $params = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
            if ($userId !== null) {
                $params[] = $userId;
            }
        }

        $sql = "INSERT IGNORE INTO words(
                text, text_lc, " .
                ($fields["tr"] != 0 ? 'translation, ' : '') .
                ($fields["ro"] != 0 ? 'romanization, ' : '') .
                ($fields["se"] != 0 ? 'sentence, ' : '') .
                "language_id, status, status_changed_at"
                . UserScopedQuery::insertColumn('words')
            . ")
            VALUES " . implode(',', $placeholders);

        Connection::preparedExecute($sql, $params);
    }
}
