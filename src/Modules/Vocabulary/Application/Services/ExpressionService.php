<?php

/**
 * Expression Service - Multi-word expression handling
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\Services;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Utilities\StringUtils;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\Escaping;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Modules\Language\Application\Services\TextParsingService;

/**
 * Service class for multi-word expression handling.
 *
 * Contains functions for finding and inserting multi-word expressions,
 * including MeCab integration for Japanese text processing.
 */
class ExpressionService
{
    private TextParsingService $textParsingService;

    /**
     * Constructor - initialize dependencies.
     *
     * @param TextParsingService|null $textParsingService Text parsing service (optional for BC)
     */
    public function __construct(?TextParsingService $textParsingService = null)
    {
        $this->textParsingService = $textParsingService ?? new TextParsingService();
    }

    /**
     * Find all occurrences of an expression using MeCab.
     *
     * @param string     $text Text to insert
     * @param string|int $lid  Language ID
     *
     * @return array<int, array{id: int, text_id: int, position: int, term: string}>
     */
    public function findMecabExpression(string $text, string|int $lid): array
    {
        $db_to_mecab = tempnam(sys_get_temp_dir(), "lukaisu_db_to_mecab");
        if ($db_to_mecab === false) {
            throw new \RuntimeException('Failed to create temporary file for MeCab expression search');
        }

        try {
            $mecab_args = " -F %m\\t%t\\t\\n -U %m\\t%t\\t\\n -E \\t\\n ";

            $mecab = $this->textParsingService->getMecabPath($mecab_args);
            $likeText = "%$text%";
            $rows = QueryBuilder::table('sentences')
                ->select(['id', 'text_id', 'first_pos', 'text'])
                ->where('language_id', '=', $lid)
                ->where('text', 'LIKE', $likeText)
                ->getPrepared();

            $parsed_text = '';
            $fp = fopen($db_to_mecab, 'w');
            if ($fp === false) {
                return [];
            }
            fwrite($fp, $text);
            fclose($fp);
            $handle = popen($mecab . escapeshellarg($db_to_mecab), "r");
            if ($handle === false) {
                return [];
            }
            while (!feof($handle)) {
                $row = fgets($handle, 16132);
                if ($row === false) {
                    break;
                }
                $arr = explode("\t", $row, 4);
                // Not a word (punctuation)
                if (
                    isset($arr[0]) && $arr[0] !== '' && $arr[0] !== "EOP"
                    && isset($arr[1]) && in_array($arr[1], ["2", "6", "7"])
                ) {
                    $parsed_text .= $arr[0] . ' ';
                }
            }
            pclose($handle);

            $occurrences = [];
            // For each sentence in database containing $text
            foreach ($rows as $record) {
                $sent = trim((string) $record['text']);
                $fp = fopen($db_to_mecab, 'w');
                if ($fp === false) {
                    continue;
                }
                fwrite($fp, $sent . "\n");
                fclose($fp);

                $handle = popen($mecab . escapeshellarg($db_to_mecab), "r");
                if ($handle === false) {
                    continue;
                }
                $parsed_sentence = '';
                // For each word in sentence
                while (!feof($handle)) {
                    $row = fgets($handle, 16132);
                    if ($row === false) {
                        break;
                    }
                    $arr = explode("\t", $row, 4);
                    // Not a word (punctuation)
                    if (
                        isset($arr[0]) && $arr[0] !== '' && $arr[0] !== "EOP"
                        && isset($arr[1]) && in_array($arr[1], ["2", "6", "7"])
                    ) {
                        $parsed_sentence .= $arr[0] . ' ';
                    }
                }

                // Finally we check if parsed text is in parsed sentence
                $seek = mb_strpos($parsed_sentence, $parsed_text);
                // For each occurrence of multi-word in sentence
                while ($seek !== false) {
                    // pos = Number of words * 2 + initial position
                    $matchCount = preg_match_all('/ /', mb_substr($parsed_sentence, 0, $seek));
                    $pos = ($matchCount !== false ? $matchCount : 0) * 2 +
                    (int) $record['first_pos'];
                    $occurrences[] = [
                        "id" => (int) $record['id'],
                        "text_id" => (int) $record['text_id'],
                        "position" => $pos,
                        "term" => $text
                    ];
                    $seek = mb_strpos($parsed_sentence, $parsed_text, $seek + 1);
                }
                pclose($handle);
            }

            return $occurrences;
        } finally {
            if (file_exists($db_to_mecab)) {
                unlink($db_to_mecab);
            }
        }
    }

    /**
     * Find all occurrences of an expression, do not use parsers like MeCab.
     *
     * @param string     $textlc Text to insert in lower case
     * @param string|int $lid    Language ID
     *
     * @return array<int, array{id: int, text_id: int, position: int, term: ?string, term_display: ?string}>
     */
    public function findStandardExpression(string $textlc, string|int $lid): array
    {
        $occurrences = [];
        $record = QueryBuilder::table('languages')
            ->where('id', '=', $lid)
            ->getPrepared()[0] ?? null;

        if ($record === null) {
            return $occurrences;
        }

        $removeSpaces = $record["remove_spaces"] == 1;
        $splitEachChar = $record['split_each_char'] != 0;
        $termchar = (string)$record['regexp_word_characters'];
        $likeTextlc = "%$textlc%";
        if ($removeSpaces && !$splitEachChar) {
            // Complex JOIN query - use raw SQL with UserScopedQuery
            $bindings = [$lid, $likeTextlc];
            $sql = "SELECT
            GROUP_CONCAT(text ORDER BY position SEPARATOR ' ') AS text, id,
            text_id, first_pos, text_id
            FROM word_occurrences
            JOIN sentences
            ON id=sentence_id AND language_id = language_id
            WHERE language_id = ?
            AND text LIKE ?
            AND word_count < 2
            GROUP BY id";
            $rows = Connection::preparedFetchAll($sql, $bindings);
        } else {
            $rows = QueryBuilder::table('sentences')
                ->where('language_id', '=', $lid)
                ->where('text', 'LIKE', $likeTextlc)
                ->getPrepared();
        }

        if ($splitEachChar) {
            $textlc = (string) preg_replace('/([^\s])/u', "$1 ", $textlc);
        }
        $wis = $textlc;
        $notermchar = "/[^$termchar]($textlc)[^$termchar]/ui";
        // For each sentence in the language containing the query
        $matches = null;
        $rSflag = false; // Flag to prevent repeat space-removal processing
        foreach ($rows as $record) {
            $string = ' ' . (string)$record['text'] . ' ';
            if ($splitEachChar) {
                $replaced = preg_replace('/([^\s])/u', "$1 ", $string);
                $string = $replaced ?? $string;
            } elseif ($removeSpaces && !$rSflag) {
                $patternPart = preg_replace('/(.)/ui', "$1[ ]*", $textlc);
                if ($patternPart !== null) {
                    preg_match(
                        '/(?<=[ ])(' . $patternPart . ')(?=[ ])/ui',
                        $string,
                        $ma
                    );
                    if (isset($ma[1]) && $ma[1] !== '') {
                        $textlc = trim($ma[1]);
                        $notermchar = "/[^$termchar]($textlc)[^$termchar]/ui";
                        $rSflag = true; // Pattern found, stop further processing
                    }
                }
            }
            $last_pos = mb_strripos($string, $textlc, 0, 'UTF-8');
            // For each occurrence of query in sentence
            while ($last_pos !== false) {
                if (
                    $splitEachChar || $removeSpaces
                    || preg_match($notermchar, " $string ", $matches, 0, $last_pos - 1)
                ) {
                    // Number of terms before group
                    $cnt = preg_match_all(
                        "/([$termchar]+)/u",
                        mb_substr($string, 0, $last_pos, 'UTF-8'),
                        $_
                    );
                    $pos = 2 * ($cnt !== false ? $cnt : 0) + (int) $record['first_pos'];
                    $txt = '';
                    $matchedTerm = $matches[1] ?? $textlc;
                    if ($matchedTerm != $textlc) {
                        $txt = $splitEachChar ? $wis : $matchedTerm;
                    }
                    if ($splitEachChar || $removeSpaces) {
                        $display = $wis;
                    } else {
                        $display = $matchedTerm;
                    }
                    $occurrences[] = [
                        "id" => (int) $record['id'],
                        "text_id" => (int) $record['text_id'],
                        "position" => $pos,
                        "term" => $txt,
                        "term_display" => $display
                    ];
                }
                // Cut the sentence to before the right-most term starts
                $string = mb_substr($string, 0, $last_pos, 'UTF-8');
                $last_pos = mb_strripos($string, $textlc, 0, 'UTF-8');
            }
        }
        return $occurrences;
    }

    /**
     * Alter the database to add a new word.
     *
     * @param string $textlc Text in lower case
     * @param int    $lid    Language ID
     * @param int    $wid    Word ID
     * @param int    $len    Number of words in the expression
     * @param int    $mode   Function mode
     *                       - 0: Default mode, do nothing special
     *                       - 1: Runs an expression inserter interactable
     *                       - 2: Return prepared statement data for batch insert
     *
     * @return array{placeholders: list<string>, params: list<mixed>}|null
     *         If $mode == 2 returns array with placeholders and params for prepared statement,
     *         null otherwise.
     */
    public function insertExpressions(string $textlc, int $lid, int $wid, int $len, int $mode): array|null
    {
        $regexp = (string)(QueryBuilder::table('languages')
            ->where('id', '=', $lid)
            ->valuePrepared('regexp_word_characters') ?? '');

        if ('MECAB' == strtoupper(trim($regexp))) {
            $occurrences = $this->findMecabExpression($textlc, $lid);
        } else {
            $occurrences = $this->findStandardExpression($textlc, $lid);
        }

        // Update the term visually through JS
        if ($mode == 0) {
            /** @var array<int, array<int, string>> $appendtext */
            $appendtext = [];
            foreach ($occurrences as $occ) {
                $txId = $occ['text_id'] ?? $occ['id'] ?? 0;
                $appendtext[$txId] = [];
                if (Settings::getZeroOrOne('showallwords', 1)) {
                    $appendtext[$txId][$occ['position']] = "&nbsp;$len&nbsp";
                } else {
                    if ('MECAB' == strtoupper(trim($regexp))) {
                        $appendtext[$txId][$occ['position']] = $occ['term'] ?? '';
                    } else {
                        $appendtext[$txId][$occ['position']] = $occ['term_display'] ?? $occ['term'] ?? '';
                    }
                }
            }
            $hex = StringUtils::toClassName(Escaping::prepareTextdata($textlc));
            $this->newMultiWordInteractable($hex, $appendtext, $wid, $len);
        }
        if (!empty($occurrences)) {
            $placeholders = [];
            $params = [];
            foreach ($occurrences as $occ) {
                $txId = $occ["text_id"] ?? $occ["id"] ?? 0;
                $placeholders[] = "(?, ?, ?, ?, ?, ?, ?)";
                $params[] = $wid;
                $params[] = $lid;
                $params[] = $txId;
                $params[] = $occ["id"];
                $params[] = $occ["position"];
                $params[] = $len;
                $params[] = $occ["term"];
            }

            if ($mode == 2) {
                // Return prepared statement data for batch insert
                return ['placeholders' => $placeholders, 'params' => $params];
            }

            $sql = "INSERT INTO word_occurrences
                 (word_id,language_id,text_id,sentence_id,position,word_count,text)
                 VALUES " . implode(',', $placeholders);
            Connection::preparedExecute($sql, $params);
        }
        return null;
    }

    /**
     * Prepare a JavaScript dialog to insert a new expression.
     *
     * @param string     $hex        Lowercase text, formatted version of the text.
     * @param array<int, array<int, string>> $multiwords Multi-words to append, format [textid][position][text]
     * @param int        $wid        Term ID
     * @param int        $len        Words count.
     *
     * @return void
     */
    public function newMultiWordInteractable(string $hex, array $multiwords, int $wid, int $len): void
    {
        $showAll = (bool)Settings::getZeroOrOne('showallwords', 1);
        $showType = $showAll ? "m" : "";

        $record = QueryBuilder::table('words')
            ->where('id', '=', $wid)
            ->getPrepared()[0] ?? null;

        $woStatus = (int)($record["status"] ?? 1);
        $attrs = [
            "class" => "click mword {$showType}wsty word$wid status" . $woStatus,
            "data_hex" => $hex,
            "data_trans" => (string)($record["translation"] ?? ''),
            "data_rom" => (string)($record["romanization"] ?? ''),
            "data_code" => $len,
            "data_status" => $woStatus,
            "data_wid" => $wid
        ];

        ?>
<script type="application/json" data-lukaisu-multiword-config>
        <?php echo json_encode([
        'attrs' => $attrs,
        'multiWords' => $multiwords,
        'hex' => $hex,
        'showAll' => $showAll
        ]); ?>
</script>
        <?php
        flush();
    }

    /**
     * Prepare a JavaScript dialog to insert a new expression (version 2).
     *
     * @param string   $hex        Lowercase text, formatted version of the text.
     * @param string[] $appendtext Text to append
     * @param int      $wid        Term ID
     * @param int      $len        Words count.
     *
     * @return void
     */
    public function newExpressionInteractable2(string $hex, array $appendtext, int $wid, int $len): void
    {
        $showAll = (bool)Settings::getZeroOrOne('showallwords', 1);
        $showType = $showAll ? "m" : "";

        $record = QueryBuilder::table('words')
            ->where('id', '=', $wid)
            ->getPrepared()[0] ?? null;

        $woStatus = (int)($record["status"] ?? 1);
        $attrs = [
            "class" => "click mword {$showType}wsty word$wid status" . $woStatus,
            "data_hex" => $hex,
            "data_trans" => (string)($record["translation"] ?? ''),
            "data_rom" => (string)($record["romanization"] ?? ''),
            "data_code" => $len,
            "data_status" => $woStatus,
            "data_wid" => $wid
        ];

        $term = array_values($appendtext)[0];

        ?>
<script type="application/json" data-lukaisu-expression-config>
        <?php echo json_encode([
        'attrs' => $attrs,
        'appendText' => $appendtext,
        'term' => $term,
        'len' => $len,
        'hex' => $hex,
        'showAll' => $showAll
        ]); ?>
</script>
        <?php
        flush();
    }
}
