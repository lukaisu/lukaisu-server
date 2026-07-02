<?php

/**
 * \file
 * \brief Shared database operations for text parsing.
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

use Lukaisu\Shared\Infrastructure\Globals;

/**
 * Shared database operations for text parsing.
 *
 * Handles SQL-based text saving, sentence/text-item registration,
 * statistics display, and multi-word expression checking.
 */
class TextParsingPersistence
{
    /**
     * Insert a processed text in the data in pure SQL way.
     *
     * @param string $text Preprocessed text to insert
     * @param int    $id   Text ID
     *
     * @return void
     */
    public static function saveWithSql(string $text, int $id): void
    {
        $file_name = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "tmpti.txt";
        $fp = fopen($file_name, 'w');
        if ($fp !== false) {
            fwrite($fp, $text);
            fclose($fp);
        }
        Connection::query("SET @order=0, @sid=1, @count = 0;");
        if ($id > 0) {
            // Get next auto-increment value for accurate sentence_id calculation
            $dbname = Globals::getDatabaseName();
            $sentencesTable = Globals::table('sentences');
            $autoInc = (int)Connection::preparedFetchValue(
                "SELECT AUTO_INCREMENT as value FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$dbname, $sentencesTable]
            );
            // Fall back to MAX+1 if AUTO_INCREMENT is not available
            if ($autoInc <= 0) {
                $autoInc = (int)Connection::fetchValue(
                    "SELECT IFNULL(MAX(`id`)+1,1) as value FROM sentences"
                    . UserScopedQuery::forTable('sentences')
                );
            }
            Connection::query("SET @sid = $autoInc;");
        }
        // LOAD DATA LOCAL INFILE does not support prepared statements for file path
        // We need to use Connection::query() here, but we escape the file path manually
        $connection = Globals::getDbConnection();
        if ($connection === null) {
            throw new \RuntimeException('Database connection not available');
        }
        $escaped_file_name = (string) mysqli_real_escape_string($connection, $file_name);
        $sql = "LOAD DATA LOCAL INFILE '$escaped_file_name'
        INTO TABLE temp_word_occurrences
        FIELDS TERMINATED BY '\\t' LINES TERMINATED BY '\\n' (@word_count, @term)
        SET
            sentence_id = @sid,
            char_position = (@count:=@count+CHAR_LENGTH(@term))+1-CHAR_LENGTH(@term),
            position = IF(
                @term LIKE '%\\r',
                CASE
                    WHEN (@term:=REPLACE(@term,'\\r','')) IS NULL THEN NULL
                    WHEN (@sid:=@sid+1) IS NULL THEN NULL
                    WHEN @count:= 0 IS NULL THEN NULL
                    ELSE @order := @order+1
                END,
                @order := @order+1
            ),
            text = @term,
            word_count = @word_count";

        // Try LOAD DATA LOCAL INFILE, fall back to INSERT if it fails
        try {
            Connection::query($sql);
        } catch (\RuntimeException $e) {
            // If LOAD DATA LOCAL INFILE is disabled, use fallback method
            if (strpos($e->getMessage(), 'LOAD DATA LOCAL INFILE is forbidden') !== false) {
                self::saveWithSqlFallback($text, $id);
            } else {
                throw $e;
            }
        }
        unlink($file_name);
    }

    /**
     * Fallback method to insert text data when LOAD DATA LOCAL INFILE is disabled.
     *
     * @param string $text Preprocessed text to insert
     * @param int    $id   Text ID
     *
     * @return void
     */
    public static function saveWithSqlFallback(string $text, int $id): void
    {
        // Get starting sentence ID
        $sid = 1;
        if ($id > 0) {
            // Get next auto-increment value for accurate sentence_id calculation
            $dbname = Globals::getDatabaseName();
            $sentencesTable = Globals::table('sentences');
            $sid = (int)Connection::preparedFetchValue(
                "SELECT AUTO_INCREMENT as value FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$dbname, $sentencesTable]
            );
            // Fall back to MAX+1 if AUTO_INCREMENT is not available
            if ($sid <= 0) {
                $sid = (int)Connection::fetchValue(
                    "SELECT IFNULL(MAX(`id`)+1,1) as value FROM sentences"
                    . UserScopedQuery::forTable('sentences')
                );
            }
        }

        $lines = explode("\n", $text);
        $order = 0;
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) < 2) {
                continue;
            }

            $word_count = (int)$parts[0];
            $term = $parts[1];

            // Handle line breaks (increase sentence ID)
            if (substr($term, -1) === "\r") {
                $term = rtrim($term, "\r");
                $order++;
                $count = 0;
                $sid++;
            } else {
                $order++;
            }

            $current_count = $count;
            $count += strlen($term) + 1;

            Connection::preparedExecute(
                "INSERT INTO temp_word_occurrences
                (sentence_id, char_position, position, text, word_count)
                VALUES (?, ?, ?, ?, ?)",
                [$sid, $current_count, $order, $term, $word_count]
            );
        }
    }

    /**
     * Echo the sentences in a text. Prepare JS data for words and word count.
     *
     * @param int $lid Language ID
     *
     * @return void
     */
    public static function checkValid(int $lid): void
    {
        $wo = $nw = array();
        $sentences = Connection::fetchAll(
            'SELECT GROUP_CONCAT(text order by position SEPARATOR "")
            Sent FROM temp_word_occurrences group by sentence_id'
        );
        echo '<h4>Sentences</h4><ol>';
        foreach ($sentences as $record) {
            echo "<li>" . \htmlspecialchars((string) ($record['Sent'] ?? ''), ENT_QUOTES, 'UTF-8') . "</li>";
        }
        echo '</ol>';
        $bindings = [$lid];
        $rows = Connection::preparedFetchAll(
            "SELECT count(temp_word_occurrences.position) cnt, if(0=temp_word_occurrences.word_count,0,1) as len,
            LOWER(temp_word_occurrences.text) as word, words.translation
            FROM temp_word_occurrences
            LEFT JOIN words ON lower(temp_word_occurrences.text)=words.text_lc AND words.language_id=?"
            . UserScopedQuery::forTablePrepared('words', $bindings, '')
            . " GROUP BY lower(temp_word_occurrences.text)",
            $bindings
        );
        foreach ($rows as $record) {
            if ($record['len'] == 1) {
                $wo[] = array(
                    \htmlspecialchars((string) ($record['word'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    $record['cnt'],
                    \htmlspecialchars((string) ($record['translation'] ?? ''), ENT_QUOTES, 'UTF-8')
                );
            } else {
                $nw[] = array(
                    htmlspecialchars((string)($record['word'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($record['cnt'] ?? ''), ENT_QUOTES, 'UTF-8')
                );
            }
        }
        // JavaScript now lives in the lukaisu repo (webapp/app/text-check.ts)
        echo '<script type="application/json" id="text-check-words-config">';
        echo json_encode(['words' => $wo, 'nonWords' => $nw], JSON_HEX_TAG | JSON_HEX_AMP);
        echo '</script>';
    }

    /**
     * Append sentences and text items in the database.
     *
     * sentence_id in temp_word_occurrences is pre-computed to match future id values.
     * When parseStandardToDatabase runs with useMaxSeID=true, it sets sentence_id
     * to MAX(id)+1, MAX(id)+2, etc. When we insert sentences here, they
     * get those exact id values via auto-increment, so sentence_id = id.
     *
     * @param int  $tid          ID of text from which insert data
     * @param int  $lid          ID of the language of the text
     * @param bool $hasmultiword Set to true to insert multi-words as well.
     *
     * @return void
     */
    public static function registerSentencesTextItems(int $tid, int $lid, bool $hasmultiword): void
    {
        // STEP 1: Insert sentences FIRST to satisfy FK constraint.
        Connection::query('SET @i=0;');
        Connection::preparedExecute(
            "INSERT INTO sentences (
                language_id, text_id, position, first_pos, text
            ) SELECT
            ?,
            ?,
            @i:=@i+1,
            MIN(IF(word_count=0, position+1, position)),
            GROUP_CONCAT(text ORDER BY position SEPARATOR \"\")
            FROM temp_word_occurrences
            GROUP BY sentence_id
            ORDER BY sentence_id",
            [$lid, $tid]
        );

        // STEP 1.5: Align sentence_id with actual id values.
        // The pre-computed sentence_id may not match actual AUTO_INCREMENT values,
        // so we update temp_word_occurrences to use the actual id from inserted sentences.
        // ROW_NUMBER() maps sentence_id rank to position, which we JOIN to get id.
        Connection::preparedExecute(
            "UPDATE temp_word_occurrences t
            JOIN (
                SELECT sentence_id AS old_seid,
                       ROW_NUMBER() OVER (ORDER BY sentence_id) AS rn
                FROM temp_word_occurrences
                GROUP BY sentence_id
            ) mapping ON t.sentence_id = mapping.old_seid
            JOIN sentences s ON s.position = mapping.rn AND s.text_id = ?
            SET t.sentence_id = s.id",
            [$tid]
        );

        // STEP 1.6: Also update tempexprs.sent if multiword expressions exist.
        // tempexprs was populated before sentences were inserted, so its `sent`
        // field also needs to be aligned with actual id values.
        if ($hasmultiword) {
            Connection::preparedExecute(
                "UPDATE tempexprs e
                JOIN (
                    SELECT sent AS old_sent,
                           ROW_NUMBER() OVER (ORDER BY sent) AS rn
                    FROM tempexprs
                    WHERE sent IS NOT NULL
                    GROUP BY sent
                ) mapping ON e.sent = mapping.old_sent
                JOIN sentences s ON s.position = mapping.rn AND s.text_id = ?
                SET e.sent = s.id",
                [$tid]
            );
        }

        // STEP 2: Insert text items. sentence_id and tempexprs.sent now equal actual id.
        if ($hasmultiword) {
            // Build SQL and bindings in lockstep. Each forTablePrepared() call
            // both appends " AND user_id = ?" to the SQL and pushes $userId
            // onto $bindings, so the bindings stay in left-to-right placeholder
            // order even with two user-scope injections inside the UNION.
            $bindings = [$lid, $tid];
            $sql = "INSERT INTO word_occurrences (
                word_id, language_id, text_id, sentence_id, position, word_count, text
            ) SELECT id, ?, ?, sent, position - (2*(n-1)) position,
            n word_count, word
            FROM tempexprs
            JOIN words
            ON text_lc = lword AND word_count = n"
                . UserScopedQuery::forTablePrepared('words', $bindings, '')
                . " WHERE lword IS NOT NULL AND language_id = ?";
            $bindings[] = $lid;
            $bindings[] = $lid;
            $bindings[] = $tid;
            $sql .= " UNION ALL
            SELECT words.id, ?, ?, temp_word_occurrences.sentence_id, temp_word_occurrences.position, temp_word_occurrences.word_count, temp_word_occurrences.text
            FROM temp_word_occurrences
            LEFT JOIN words
            ON LOWER(temp_word_occurrences.text) = words.text_lc AND temp_word_occurrences.word_count=1 AND words.language_id = ?";
            $bindings[] = $lid;
            $sql .= UserScopedQuery::forTablePrepared('words', $bindings, '')
                // ORDER BY in a UNION must reference the first SELECT's output
                // column aliases (position, word_count), not table-qualified names.
                . " ORDER BY position, word_count";

            $stmt = Connection::prepare($sql);
            $stmt->bindValues($bindings);
            $stmt->execute();
        } else {
            $bindings = [$lid, $tid, $lid];
            Connection::preparedExecute(
                "INSERT INTO word_occurrences (
                    word_id, language_id, text_id, sentence_id, position, word_count, text
                )
                SELECT words.id, ?, ?, temp_word_occurrences.sentence_id, temp_word_occurrences.position, temp_word_occurrences.word_count, temp_word_occurrences.text
                FROM temp_word_occurrences
                LEFT JOIN words
                ON LOWER(temp_word_occurrences.text) = words.text_lc AND temp_word_occurrences.word_count=1 AND words.language_id = ?"
                . UserScopedQuery::forTablePrepared('words', $bindings, '')
                . " ORDER BY temp_word_occurrences.position, temp_word_occurrences.word_count",
                $bindings
            );
        }
    }

    /**
     * Display statistics about a text.
     *
     * @param int  $lid        Language ID
     * @param bool $rtlScript  true if language is right-to-left
     * @param bool $multiwords Display if text has multi-words
     *
     * @return void
     */
    public static function displayStatistics(int $lid, bool $rtlScript, bool $multiwords): void
    {
        $mw = array();
        if ($multiwords) {
            $bindings = [$lid];
            $rows = Connection::preparedFetchAll(
                "SELECT COUNT(id) cnt, n as len,
                LOWER(text) AS word, translation
                FROM tempexprs
                JOIN words
                ON text_lc = lword AND word_count = n"
                . UserScopedQuery::forTablePrepared('words', $bindings, '')
                . " WHERE lword IS NOT NULL AND language_id = ?
                GROUP BY id ORDER BY text_lc",
                $bindings
            );
            foreach ($rows as $record) {
                $mw[] = array(
                    htmlspecialchars((string)($record['word'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    $record['cnt'],
                    htmlspecialchars((string)($record['translation'] ?? ''), ENT_QUOTES, 'UTF-8')
                );
            }
        }
        // JavaScript now lives in the lukaisu repo (webapp/app/text-check.ts)
        echo '<script type="application/json" id="text-check-config">';
        echo json_encode([
            'words' => [], // Will be populated from text-check-words-config
            'multiWords' => $mw,
            'nonWords' => [], // Will be populated from text-check-words-config
            'rtlScript' => $rtlScript
        ], JSON_HEX_TAG | JSON_HEX_AMP);
        echo '</script>';
    }

    /**
     * Get all multi-word expression lengths for a language.
     *
     * @param int $lid Language ID
     *
     * @return int[] Array of distinct word counts (e.g., [2, 3] for 2-word and 3-word expressions)
     */
    public static function getMultiWordLengths(int $lid): array
    {
        $wl = [];
        $rows = QueryBuilder::table('words')
            ->select(['DISTINCT(word_count) as word_count'])
            ->where('language_id', '=', $lid)
            ->where('word_count', '>', 1)
            ->getPrepared();

        foreach ($rows as $record) {
            $wl[] = (int)$record['word_count'];
        }

        return $wl;
    }

    /**
     * Check a language that contains expressions.
     *
     * @param int[] $wl All the different expression length in the language.
     *
     * @return void
     */
    public static function checkExpressions(array $wl): void
    {
        $wl_max = 0;
        $mw_sql = '';
        foreach ($wl as $word_length) {
            if ($wl_max < $word_length) {
                $wl_max = $word_length;
            }
            $mw_sql .= ' WHEN ' . $word_length .
            ' THEN @a' . ($word_length * 2 - 1);
        }
        $set_wo_sql = $set_wo_sql_2 = $del_wo_sql = $init_var = '';
        // For all possible multi-words length
        for ($i = $wl_max * 2 - 1; $i > 1; $i--) {
            $set_wo_sql .= "WHEN (@a$i := @a" . ($i - 1) . ") IS NULL THEN NULL ";
            $set_wo_sql_2 .= "WHEN (@a$i := @a" . ($i - 2) . ") IS NULL THEN NULL ";
            $del_wo_sql .= "WHEN (@a$i := @a0) IS NULL THEN NULL ";
            $init_var .= "@a$i=0,";
        }
        // @a0 is always 0? @f always '' but necessary to force code execution
        Connection::query(
            "SET $init_var@a1=0, @a0=0, @se_id=0, @c='', @d=0, @f='', @ti_or=0;"
        );
        // Create a table to store length of each terms
        Connection::query(
            "CREATE TEMPORARY TABLE IF NOT EXISTS numbers(
                n tinyint(3) unsigned NOT NULL
            );"
        );
        Connection::execute("TRUNCATE TABLE numbers");
        Connection::query(
            "INSERT IGNORE INTO numbers(n) VALUES (" .
            implode('),(', $wl) .
            ');'
        );
        // Store garbage
        Connection::query(
            "CREATE TABLE IF NOT EXISTS tempexprs (
                sent mediumint unsigned,
                word varchar(250),
                lword varchar(250),
                position smallint unsigned,
                n tinyint(3) unsigned NOT NULL
            )"
        );
        Connection::execute("TRUNCATE TABLE tempexprs");
        Connection::query(
            "INSERT IGNORE INTO tempexprs
            (sent, word, lword, position, n)
            -- straight_join may be irrelevant as the query is less skewed
            SELECT straight_join
            IF(
                @se_id=sentence_id and @ti_or=position,
                IF((@ti_or:=position+@a0) is null,sentence_id,sentence_id),
                IF(
                    @se_id=sentence_id,
                    IF(
                        (@d=1) and (0<>word_count),
                        CASE $set_wo_sql_2
                            WHEN (@a1:=char_position+@a0) IS NULL THEN NULL
                            WHEN (@se_id:=sentence_id+@a0) IS NULL THEN NULL
                            WHEN (@ti_or:=position+@a0) IS NULL THEN NULL
                            WHEN (@c:=concat(@c,text)) IS NULL THEN NULL
                            WHEN (@d:=(0<>word_count)+@a0) IS NULL THEN NULL
                            ELSE sentence_id
                        END,
                        CASE $set_wo_sql
                            WHEN (@a1:=char_position+@a0) IS NULL THEN NULL
                            WHEN (@se_id:=sentence_id+@a0) IS NULL THEN NULL
                            WHEN (@ti_or:=position+@a0) IS NULL THEN NULL
                            WHEN (@c:=concat(@c,text)) IS NULL THEN NULL
                            WHEN (@d:=(0<>word_count)+@a0) IS NULL THEN NULL
                            ELSE sentence_id
                        END
                    ),
                    CASE $del_wo_sql
                        WHEN (@a1:=char_position+@a0) IS NULL THEN NULL
                        WHEN (@se_id:=sentence_id+@a0) IS NULL THEN NULL
                        WHEN (@ti_or:=position+@a0) IS NULL THEN NULL
                        WHEN (@c:=concat(text,@f)) IS NULL THEN NULL
                        WHEN (@d:=(0<>word_count)+@a0) IS NULL THEN NULL
                        ELSE sentence_id
                    END
                )
            ) sent,
            if(
                @d=0,
                NULL,
                if(
                    CRC32(@z:=substr(@c,CASE n$mw_sql END))<>CRC32(LOWER(@z)),
                    @z,
                    ''
                )
            ) word,
            if(@d=0 or ''=@z, NULL, lower(@z)) lword,
            position,
            n
            FROM numbers , temp_word_occurrences"
        );
    }
}
