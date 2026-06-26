<?php

/**
 * \file
 * \brief Japanese text parsing with MeCab.
 *
 * PHP version 8.1
 *
 * @category Database
 * @package  Lukaisu
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Infrastructure\Database;

use Lukaisu\Modules\Language\Application\Services\TextParsingService;

/**
 * Japanese text parsing using MeCab.
 *
 * Handles splitting, previewing, and database insertion for Japanese text.
 */
class JapaneseTextParser
{
    /**
     * Split Japanese text into sentences (split-only mode).
     *
     * @param string $text Preprocessed text
     *
     * @return string[] Array of sentences
     *
     * @psalm-return non-empty-list<string>
     */
    public static function splitJapaneseSentences(string $text): array
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = trim($text);
        $text = preg_replace("/[\n]+/u", "\n¶", $text) ?? $text;
        return explode("\n", $text);
    }

    /**
     * Display preview HTML for Japanese text.
     *
     * @param string $text Preprocessed text
     *
     * @return void
     */
    public static function displayJapanesePreview(string $text): void
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = trim($text);
        echo '<div id="check_text" style="margin-right:50px;">
        <h2>Text</h2>
        <p>' . str_replace("\n", "<br /><br />", \htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    /**
     * Parse Japanese text with MeCab and insert into temp_word_occurrences.
     *
     * @param string $text         Preprocessed text
     * @param bool   $useMaxSeID   Whether to query for max sentence ID (true for existing texts)
     *
     * @return void
     */
    public static function parseJapaneseToDatabase(string $text, bool $useMaxSeID): void
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = trim($text);

        $file_name = tempnam(sys_get_temp_dir(), "tmpti");
        if ($file_name === false) {
            throw new \RuntimeException('Failed to create temporary file for MeCab parsing');
        }

        try {
            // We use the format "word  num num" for all nodes
            $mecab_args = " -F %m\\t%t\\t%h\\n -U %m\\t%t\\t%h\\n -E EOP\\t3\\t7\\n";
            $mecab_args .= " -o " . escapeshellarg($file_name) . " ";
            $mecab = (new TextParsingService())->getMecabPath($mecab_args);

            // WARNING: \n is converted to PHP_EOL here!
            $handle = popen($mecab, 'w');
            if ($handle !== false) {
                fwrite($handle, $text);
                pclose($handle);
            }

            Connection::execute(
                "CREATE TEMPORARY TABLE IF NOT EXISTS tempword_occurrences (
                    char_position smallint(5) unsigned NOT NULL,
                    sentence_id mediumint(8) unsigned NOT NULL,
                    position smallint(5) unsigned NOT NULL,
                    word_count tinyint(3) unsigned NOT NULL,
                    text varchar(250) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL
                ) DEFAULT CHARSET=utf8"
            );
            $handle = fopen($file_name, 'r');
            $mecabed = '';
            if ($handle !== false) {
                $size = filesize($file_name);
                if ($size !== false && $size > 0) {
                    $result = fread($handle, $size);
                    $mecabed = $result !== false ? $result : '';
                }
                fclose($handle);
            }
            $values = array();
            $order = 0;
            $sid = 1;
            if ($useMaxSeID) {
                $sid = (int)Connection::fetchValue(
                    "SELECT IFNULL(MAX(`id`)+1,1) as value FROM sentences"
                    . UserScopedQuery::forTable('sentences')
                );
            }
            $term_type = 0;
            $last_node_type = 0;
            $count = 0;
            $row = array(0, 0, 0, "", 0);
            $separator = mb_chr(9);
            if ($separator === false) {
                $separator = "\t";
            }
            foreach (explode(PHP_EOL, $mecabed) as $line) {
                if (trim($line) == "") {
                    continue;
                }
                $parts = explode($separator, $line);
                $term = $parts[0] ?? '';
                $node_type = $parts[1] ?? '';
                $third = $parts[2] ?? '';
                if ($term_type == 2 || $term == 'EOP' && $third == '7') {
                    $sid += 1;
                }
                $row[0] = $sid; // sentence_id
                $row[1] = $count + 1; // char_position
                $count += mb_strlen($term);
                $last_term_type = $term_type;
                if ($third == '7') {
                    if ($term == 'EOP') {
                        $term = '¶';
                    }
                    $term_type = 2;
                } elseif (in_array($node_type, ['2', '6', '7', '8'])) {
                    $term_type = 0;
                } else {
                    $term_type = 1;
                }

                // Increase word order:
                // Once if the current or the previous term were words
                // Twice if current or the previous were not of unmanaged type
                $order += (int)($term_type == 0 && $last_term_type == 0) +
                (int)($term_type != 1 || $last_term_type != 1);
                $row[2] = $order; // position
                $row[3] = $term; // text (no escaping needed for prepared statement)
                $row[4] = $term_type == 0 ? 1 : 0; // word_count
                $values[] = $row;
                // Special case for kazu (numbers)
                if ($last_node_type == 8 && $node_type == 8) {
                    $lastKey = array_key_last($values);
                    // $lastKey is int<0, max> since we just added an element
                    // We need at least 2 elements to access previous
                    if ($lastKey > 0 && isset($values[$lastKey - 1][3])) {
                        // Concatenate the previous value with the current term
                        $values[$lastKey - 1][3] = $values[$lastKey - 1][3] . $term;
                        // Remove last element to avoid repetition
                        array_pop($values);
                    }
                }
                $last_node_type = $node_type;
            }

            // Build multi-row INSERT with prepared statement
            // Generate placeholders for all rows: (?, ?, ?, ?, ?), (?, ?, ?, ?, ?), ...
            $placeholders = array();
            $flatParams = array();
            foreach ($values as $row) {
                $placeholders[] = "(?, ?, ?, ?, ?)";
                // Flatten the row values into a single array for binding
                $flatParams[] = $row[0]; // sentence_id
                $flatParams[] = $row[1]; // char_position
                $flatParams[] = $row[2]; // position
                $flatParams[] = $row[3]; // text
                $flatParams[] = $row[4]; // word_count
            }

            if (!empty($placeholders)) {
                Connection::preparedExecute(
                    "INSERT INTO tempword_occurrences (
                        sentence_id, char_position, position, text, word_count
                    ) VALUES " . implode(',', $placeholders),
                    $flatParams
                );
            }
            // Delete elements position=@order
            Connection::preparedExecute(
                "DELETE FROM tempword_occurrences WHERE position=?",
                [$order]
            );
            Connection::query(
                "INSERT INTO temp_word_occurrences (
                    char_position, sentence_id, position, word_count, text
                )
                SELECT MIN(char_position) s, sentence_id, position, word_count,
                group_concat(text ORDER BY char_position SEPARATOR '')
                FROM tempword_occurrences
                GROUP BY position"
            );
            Connection::execute("DROP TABLE tempword_occurrences");
        } finally {
            if (file_exists($file_name)) {
                unlink($file_name);
            }
        }
    }

    /**
     * Parse a Japanese text using MeCab and add it to the database.
     *
     * @param string $text Text to parse.
     * @param int    $id   Text ID. If $id = -1 print results,
     *                     if $id = -2 return splitted texts
     *
     * @return null|string[] Splitted sentence if $id = -2
     *
     * @psalm-return non-empty-list<string>|null
     *
     * @internal Use TextParsing::splitIntoSentences(), parseAndDisplayPreview(), or parseAndSave() instead.
     */
    public static function parseJapanese(string $text, int $id): ?array
    {
        if ($id == -2) {
            return self::splitJapaneseSentences($text);
        }

        if ($id == -1) {
            self::displayJapanesePreview($text);
        }

        self::parseJapaneseToDatabase($text, $id > 0);
        return null;
    }
}
