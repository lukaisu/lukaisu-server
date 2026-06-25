<?php

/**
 * Import Utilities - Shared helpers for term import operations
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
use Lukaisu\Shared\Infrastructure\Database\Escaping;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Shared utility methods used by both simple and complete import modes.
 *
 * Provides language data retrieval, file handling, delimiter parsing,
 * column mapping, and post-import operations (multiword handling, linking).
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class ImportUtilities
{
    /**
     * Maximum number of rows to insert in a single batch.
     * Keeps memory usage reasonable for large imports.
     */
    public const BATCH_SIZE = 500;

    /**
     * Get language data for a specific language.
     *
     * @param int $langId Language ID
     *
     * @return array|null Language data or null if not found
     */
    public function getLanguageData(int $langId): ?array
    {
        return QueryBuilder::table('languages')
            ->where('LgID', '=', $langId)
            ->firstPrepared();
    }

    /**
     * Check if local infile is enabled in MySQL and PHP.
     *
     * Note: Even if MySQL server has local_infile enabled, PHP might not allow it.
     * We check both the server setting and the PHP mysqli setting.
     *
     * @return bool True if local_infile is enabled on both server and client
     */
    public function isLocalInfileEnabled(): bool
    {
        // Check MySQL server setting
        /** @var int|string|null $serverValue */
        $serverValue = Connection::fetchValue("SELECT @@GLOBAL.local_infile as value");
        if (!in_array($serverValue, [1, '1', 'ON'])) {
            return false;
        }

        // Check PHP mysqli setting
        $phpValue = ini_get('mysqli.allow_local_infile');
        if ($phpValue === false || $phpValue === '' || $phpValue === '0') {
            return false;
        }

        return true;
    }

    /**
     * Parse column mapping from request.
     *
     * @param array $columns      Column assignments from form (Col1-Col5)
     * @param bool  $removeSpaces Whether language removes spaces
     *
     * @return array{columns: array<int, string>, fields: array{txt: int, tr: int, ro: int, se: int, tl: int}}
     */
    public function parseColumnMapping(array $columns, bool $removeSpaces): array
    {
        /** @var array<int, string> $col */
        $col = [];
        $fields = ["txt" => 0, "tr" => 0, "ro" => 0, "se" => 0, "tl" => 0];

        // Remove duplicates and keep unique
        $columns = array_unique($columns);

        $keys = array_keys($columns);
        $max = count($keys) > 0 ? max($keys) : 0;
        for ($j = 1; $j <= $max; $j++) {
            if (!isset($columns[$j])) {
                $col[$j] = '@dummy';
            } else {
                switch ($columns[$j]) {
                    case 'w':
                        $col[$j] = $removeSpaces ? '@wotext' : 'text';
                        $fields["txt"] = $j;
                        break;
                    case 't':
                        $col[$j] = 'translation';
                        $fields["tr"] = $j;
                        break;
                    case 'r':
                        $col[$j] = 'romanization';
                        $fields["ro"] = $j;
                        break;
                    case 's':
                        $col[$j] = 'sentence';
                        $fields["se"] = $j;
                        break;
                    case 'g':
                        $col[$j] = '@taglist';
                        $fields["tl"] = $j;
                        break;
                    case 'x':
                        if ($j == $max) {
                            unset($col[$j]);
                        } else {
                            $col[$j] = '@dummy';
                        }
                        break;
                }
            }
        }

        return ['columns' => $col, 'fields' => $fields];
    }

    /**
     * Get delimiter character from tab type.
     *
     * @param string $tabType Tab type (c, t, h)
     *
     * @return string Delimiter character
     */
    public function getDelimiter(string $tabType): string
    {
        return match ($tabType) {
            'c' => ',',
            'h' => '#',
            default => "\t",
        };
    }

    /**
     * Get delimiter for SQL LOAD DATA statement.
     *
     * @param string $tabType Tab type (c, t, h)
     *
     * @return string SQL delimiter string
     */
    public function getSqlDelimiter(string $tabType): string
    {
        return match ($tabType) {
            'c' => ',',
            'h' => '#',
            default => "\\t",
        };
    }

    /**
     * Create a temporary file from text input.
     *
     * @param string $content Text content to write
     *
     * @return string Path to temporary file
     */
    public function createTempFile(string $content): string
    {
        $fileName = tempnam(sys_get_temp_dir(), "Lukaisu Server");
        if ($fileName === false) {
            throw new \RuntimeException('Failed to create temporary file for import');
        }
        $temp = fopen($fileName, "w");
        if ($temp === false) {
            throw new \RuntimeException('Failed to open temporary file for writing');
        }
        fwrite($temp, Escaping::prepareTextdata($content));
        fclose($temp);

        // Defense-in-depth: if a fatal error short-circuits the
        // controller's `finally { unlink(...) }` block, this hook
        // still removes the temp file at shutdown. Idempotent — a
        // successful clean path that already unlinked the file
        // produces a harmless ENOENT swallowed by the @.
        register_shutdown_function(static function () use ($fileName): void {
            if (is_file($fileName)) {
                @unlink($fileName);
            }
        });

        return $fileName;
    }

    /**
     * Handle multi-word expressions after import.
     *
     * @param int    $langId     Language ID
     * @param string $lastUpdate Last update timestamp
     *
     * @return void
     */
    public function handleMultiwords(int $langId, string $lastUpdate): void
    {
        $mwords = QueryBuilder::table('words')
            ->where('word_count', '>', 1)
            ->where('created_at', '>', $lastUpdate)
            ->countPrepared();

        if ($mwords > 40) {
            // Bulk update: delete and recreate all text items
            QueryBuilder::table('sentences')
                ->where('language_id', '=', $langId)
                ->delete();
            QueryBuilder::table('word_occurrences')
                ->where('language_id', '=', $langId)
                ->delete();
            \Lukaisu\Shared\Infrastructure\Database\Maintenance::adjustAutoIncrement('sentences', 'id');

            $rows = QueryBuilder::table('texts')
                ->select(['id', 'text'])
                ->where('language_id', '=', $langId)
                ->orderBy('id')
                ->getPrepared();
            foreach ($rows as $record) {
                $txtid = (int) $record["id"];
                $txttxt = (string) $record["text"];
                \Lukaisu\Shared\Infrastructure\Database\TextParsing::parseAndSave($txttxt, $langId, $txtid);
            }
        } elseif ($mwords > 0) {
            // Update individual multi-word expressions
            $allPlaceholders = [];
            $allParams = [];
            $rows = QueryBuilder::table('words')
                ->select(['id', 'text_lc', 'word_count'])
                ->where('word_count', '>', 1)
                ->where('created_at', '>', $lastUpdate)
                ->getPrepared();
            foreach ($rows as $record) {
                $len = (int) $record['word_count'];
                $wid = (int) $record['id'];
                $textlc = (string) $record['text_lc'];
                $expressionService = new ExpressionService();
                $result = $expressionService->insertExpressions($textlc, $langId, $wid, $len, 2);
                if ($result !== null) {
                    $allPlaceholders = array_merge($allPlaceholders, $result['placeholders']);
                    $allParams = array_merge($allParams, $result['params']);
                }
            }

            if (!empty($allPlaceholders)) {
                $sql = "INSERT INTO word_occurrences (
                    word_id, language_id, text_id, sentence_id, position, word_count, text
                ) VALUES " . implode(',', $allPlaceholders);
                Connection::preparedExecute($sql, $allParams);
            }
        }
    }

    /**
     * Get the last word status change timestamp.
     *
     * @return string|null Last update timestamp
     */
    public function getLastWordUpdate(): ?string
    {
        $result = QueryBuilder::table('words')
            ->select(['MAX(status_changed_at) AS max_date'])
            ->first();
        return $result !== null ? (string)$result['max_date'] : null;
    }

    /**
     * Link imported words to text items.
     *
     * @return void
     */
    public function linkWordsToTextItems(): void
    {
        $bindings = [];
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings);
        $sql = "UPDATE words
            JOIN word_occurrences
            ON word_count=1 AND word_id IS NULL AND lower(text)=text_lc AND language_id = language_id"
            . $userScope
            . " SET word_id=id";
        Connection::preparedExecute($sql, $bindings);
    }

    /**
     * Count imported terms.
     *
     * @param string $lastUpdate Last update timestamp
     *
     * @return int Number of imported terms
     */
    public function countImportedTerms(string $lastUpdate): int
    {
        return QueryBuilder::table('words')
            ->where('status_changed_at', '>', $lastUpdate)
            ->countPrepared();
    }

    /**
     * Get imported terms for display.
     *
     * @param string $lastUpdate Last update timestamp
     * @param int    $offset     Offset for pagination
     * @param int    $limit      Limit for pagination
     *
     * @return array Imported terms data
     */
    public function getImportedTerms(string $lastUpdate, int $offset, int $limit): array
    {
        $bindings = [$lastUpdate];
        $userScope = UserScopedQuery::forTablePrepared('words', $bindings, 'w');
        $bindings[] = $offset;
        $bindings[] = $limit;

        $sql = "SELECT w.id, w.text, w.text_lc, w.translation,
                w.romanization, w.sentence, w.status,
                GROUP_CONCAT(t.text ORDER BY t.text SEPARATOR ', ') as taglist,
                CASE WHEN w.sentence != '' AND w.sentence LIKE CONCAT('%{', w.text, '}%')
                    THEN 1 ELSE 0 END as SentOK
            FROM words w
            LEFT JOIN word_tag_map wt ON w.id = wt.word_id
            LEFT JOIN tags t ON wt.tag_id = t.id
            WHERE w.status_changed_at > ?{$userScope}
            GROUP BY w.id
            ORDER BY w.text
            LIMIT ?, ?";

        return Connection::preparedFetchAll($sql, $bindings);
    }

    /**
     * Get right-to-left setting for a language.
     *
     * @param int $langId Language ID
     *
     * @return bool True if language is RTL
     */
    public function isRightToLeft(int $langId): bool
    {
        return (bool) QueryBuilder::table('languages')
            ->where('LgID', '=', $langId)
            ->valuePrepared('LgRightToLeft');
    }
}
