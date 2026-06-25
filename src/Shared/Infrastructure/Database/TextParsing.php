<?php

/**
 * \file
 * \brief Text parsing and processing utilities (facade).
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

use Lukaisu\Shared\Infrastructure\Exception\DatabaseException;

/**
 * Text parsing and processing utilities (facade).
 *
 * Delegates to JapaneseTextParser, StandardTextParser, and TextParsingPersistence.
 *
 * @since 3.0.0
 */
class TextParsing
{
    /**
     * Split text into sentences without database operations.
     *
     * Use this method when you only need to split text into sentences
     * without saving to the database (e.g., for long text splitting).
     *
     * @param string $text Text to parse
     * @param int    $lid  Language ID
     *
     * @return string[] Array of sentences
     *
     * @psalm-return non-empty-list<string>
     */
    public static function splitIntoSentences(string $text, int $lid): array
    {
        $result = self::prepare($text, -2, $lid);
        return $result ?? [''];
    }

    /**
     * Parse text and display preview HTML for validation.
     *
     * Use this method for the text checking UI. Outputs HTML directly
     * to show parsed sentences and word statistics.
     *
     * @param string $text Text to parse
     * @param int    $lid  Language ID
     *
     * @return void
     */
    public static function parseAndDisplayPreview(string $text, int $lid): void
    {
        $record = QueryBuilder::table('languages')
            ->select(['right_to_left'])
            ->where('id', '=', $lid)
            ->firstPrepared();

        if ($record === null) {
            throw DatabaseException::recordNotFound('languages', 'id', $lid);
        }
        $rtlScript = (bool)$record['right_to_left'];

        // Parse text and display preview HTML (id=-1 triggers preview display in prepare)
        self::prepare($text, -1, $lid);

        // Display sentences and word statistics
        TextParsingPersistence::checkValid($lid);

        // Get multi-word expressions
        $wl = TextParsingPersistence::getMultiWordLengths($lid);

        // Process multi-word expressions if any exist
        if (!empty($wl)) {
            TextParsingPersistence::checkExpressions($wl);
        }

        // Display statistics
        TextParsingPersistence::displayStatistics($lid, $rtlScript, !empty($wl));

        // Clean up
        QueryBuilder::table('temp_word_occurrences')->truncate();
    }

    /**
     * Parse text and save to database.
     *
     * Use this method when creating or updating texts. Parses the text
     * and inserts sentences and text items into the database.
     *
     * @param string $text   Text to parse
     * @param int    $lid    Language ID
     * @param int    $textId Text ID (must be positive)
     *
     * @return void
     *
     * @throws \InvalidArgumentException If textId is not positive
     */
    public static function parseAndSave(string $text, int $lid, int $textId): void
    {
        if ($textId <= 0) {
            throw new \InvalidArgumentException(
                "Text ID must be positive, got: $textId"
            );
        }

        $record = QueryBuilder::table('languages')
            ->select(['id'])
            ->where('id', '=', $lid)
            ->firstPrepared();

        if ($record === null) {
            throw DatabaseException::recordNotFound('languages', 'id', $lid);
        }

        // Parse text into temp_word_occurrences (id>0 uses MAX(id)+1 for sentence IDs)
        self::prepare($text, $textId, $lid);

        // Get multi-word expressions
        $wl = TextParsingPersistence::getMultiWordLengths($lid);

        // Process multi-word expressions if any exist
        if (!empty($wl)) {
            TextParsingPersistence::checkExpressions($wl);
        }

        // Register sentences and text items in database
        TextParsingPersistence::registerSentencesTextItems($textId, $lid, !empty($wl));

        // Clean up
        QueryBuilder::table('temp_word_occurrences')->truncate();
    }

    /**
     * Check/preview text and return parsing statistics without saving.
     *
     * Use this method to get text statistics for preview purposes.
     * Does not output any HTML or save to database.
     *
     * @param string $text Text to parse
     * @param int    $lid  Language ID
     *
     * @return array{sentences: int, words: int, unknownPercent: float, preview: string}
     */
    public static function checkText(string $text, int $lid): array
    {
        $settings = StandardTextParser::getLanguageSettings($lid);

        if ($settings === null) {
            return [
                'sentences' => 0,
                'words' => 0,
                'unknownPercent' => 100.0,
                'preview' => ''
            ];
        }

        // Prepare text into temp_word_occurrences
        self::prepare($text, -1, $lid);

        // Get sentence count
        $sentences = Connection::fetchAll(
            'SELECT GROUP_CONCAT(text ORDER BY position SEPARATOR "")
            AS Sent FROM temp_word_occurrences GROUP BY sentence_id'
        );
        $sentenceCount = count($sentences);

        // Build preview from first few sentences
        $preview = '';
        $previewSentences = array_slice($sentences, 0, 3);
        foreach ($previewSentences as $record) {
            if ($preview !== '') {
                $preview .= ' ';
            }
            $preview .= (string) ($record['Sent'] ?? '');
        }
        if (count($sentences) > 3) {
            $preview .= '...';
        }

        // Get word statistics
        $bindings = [$lid];
        $rows = Connection::preparedFetchAll(
            "SELECT COUNT(temp_word_occurrences.position) AS cnt, IF(0=temp_word_occurrences.word_count,0,1) AS len,
            LOWER(temp_word_occurrences.text) AS word, words.translation
            FROM temp_word_occurrences
            LEFT JOIN words ON LOWER(temp_word_occurrences.text)=words.text_lc AND words.language_id=?"
            . UserScopedQuery::forTablePrepared('words', $bindings, '')
            . " GROUP BY LOWER(temp_word_occurrences.text)",
            $bindings
        );

        $totalWords = 0;
        $unknownWords = 0;

        foreach ($rows as $record) {
            if ($record['len'] == 1) {
                $totalWords += (int) $record['cnt'];
                // Word is unknown if it has no translation
                if (empty($record['translation'])) {
                    $unknownWords += (int) $record['cnt'];
                }
            }
        }

        $unknownPercent = $totalWords > 0
            ? round(($unknownWords / $totalWords) * 100, 1)
            : 100.0;

        // Clean up temp_word_occurrences
        QueryBuilder::table('temp_word_occurrences')->truncate();

        return [
            'sentences' => $sentenceCount,
            'words' => $totalWords,
            'unknownPercent' => $unknownPercent,
            'preview' => $preview
        ];
    }

    /**
     * Like {@see checkText()} but returns the *structured* parse preview the
     * bundled "check a text" page needs: the reconstructed sentences, and the
     * distinct word / non-word tokens with their occurrence counts (each word
     * carrying its saved translation, or '' when unknown). Mirrors the local
     * router's `checkText` (repositories/texts.ts) so the on-device and
     * server-backed previews are identical — the gap that previously kept
     * `text-check.html` working only offline.
     *
     * @param string $text The raw text to parse.
     * @param int    $lid  The language id.
     *
     * @return array{
     *   sentences: list<string>,
     *   words: list<array{0: string, 1: int, 2: string}>,
     *   nonWords: list<array{0: string, 1: int}>,
     *   multiWords: list<array{0: string, 1: int, 2: string}>,
     *   rtlScript: bool
     * }
     */
    public static function checkTextDetailed(string $text, int $lid): array
    {
        $empty = [
            'sentences' => [],
            'words' => [],
            'nonWords' => [],
            'multiWords' => [],
            'rtlScript' => false,
        ];

        $settings = StandardTextParser::getLanguageSettings($lid);
        if ($settings === null) {
            return $empty;
        }

        $rtlScript = false;
        /** @var array{right_to_left?: mixed}|null $langRow */
        $langRow = Connection::preparedFetchOne(
            'SELECT right_to_left FROM languages WHERE id = ?',
            [$lid]
        );
        if (is_array($langRow)) {
            $rtlScript = (bool) ($langRow['right_to_left'] ?? false);
        }

        // Tokenize into the temp table (same as checkText()).
        self::prepare($text, -1, $lid);

        $sentences = [];
        $sentenceRows = Connection::fetchAll(
            'SELECT GROUP_CONCAT(text ORDER BY position SEPARATOR "")
            AS Sent FROM temp_word_occurrences GROUP BY sentence_id'
        );
        foreach ($sentenceRows as $record) {
            $sentences[] = (string) ($record['Sent'] ?? '');
        }

        $bindings = [$lid];
        $rows = Connection::preparedFetchAll(
            "SELECT COUNT(temp_word_occurrences.position) AS cnt, IF(0=temp_word_occurrences.word_count,0,1) AS len,
            LOWER(temp_word_occurrences.text) AS word, words.translation
            FROM temp_word_occurrences
            LEFT JOIN words ON LOWER(temp_word_occurrences.text)=words.text_lc AND words.language_id=?"
            . UserScopedQuery::forTablePrepared('words', $bindings, '')
            . " GROUP BY LOWER(temp_word_occurrences.text)",
            $bindings
        );

        $words = [];
        $nonWords = [];
        foreach ($rows as $record) {
            $token = (string) ($record['word'] ?? '');
            $count = (int) ($record['cnt'] ?? 0);
            if ((int) ($record['len'] ?? 0) === 1) {
                $words[] = [$token, $count, (string) ($record['translation'] ?? '')];
            } else {
                $nonWords[] = [$token, $count];
            }
        }

        QueryBuilder::table('temp_word_occurrences')->truncate();

        return [
            'sentences' => $sentences,
            'words' => $words,
            'nonWords' => $nonWords,
            // Multi-word expressions aren't matched in the preview (parity with
            // the offline tokenizer, which never creates them on-device).
            'multiWords' => [],
            'rtlScript' => $rtlScript,
        ];
    }

    /**
     * Pre-parse the input text before a definitive parsing by a specialized parser.
     *
     * @param string $text Text to parse
     * @param int    $id   Text ID
     * @param int    $lid  Language ID
     *
     * @return null|string[] If $id = -2 return a splitted version of the text
     *
     * @psalm-return non-empty-list<string>|null
     *
     * @internal Use splitIntoSentences(), parseAndDisplayPreview(), or parseAndSave() instead.
     */
    private static function prepare(string $text, int $id, int $lid): ?array
    {
        $record = QueryBuilder::table('languages')
            ->where('id', '=', $lid)
            ->firstPrepared();

        // Return null if language not found
        if ($record === null) {
            return null;
        }

        $termchar = (string)$record['regexp_word_characters'];
        $replace = explode("|", (string) $record['character_substitutions']);
        $text = Escaping::prepareTextdata($text);
        QueryBuilder::table('temp_word_occurrences')->truncate();

        // because of sentence special characters
        $text = str_replace(array('}', '{'), array(']', '['), $text);
        foreach ($replace as $value) {
            $fromto = explode("=", trim($value));
            if (count($fromto) >= 2) {
                $text = str_replace(trim($fromto[0]), trim($fromto[1]), $text);
            }
        }

        if ('MECAB' == strtoupper(trim($termchar))) {
            return JapaneseTextParser::parseJapanese($text, $id);
        }
        return StandardTextParser::parseStandard($text, $id, $lid);
    }
}
