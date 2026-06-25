<?php

/**
 * Parse Text Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Application\UseCases;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\TextParsing;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Use case for text parsing operations.
 *
 * Handles text parsing preview and validation without saving,
 * useful for checking text before import.
 *
 * @since 3.0.0
 */
class ParseText
{
    /**
     * Check/preview text parsing without saving.
     *
     * @param string $text       Text content to parse
     * @param int    $languageId Language ID
     *
     * @return array{sentences: int, words: int, unknownPercent: float, preview: string}
     */
    public function execute(string $text, int $languageId): array
    {
        // Get language parsing settings
        $bindings = [$languageId];
        $langSettings = Connection::preparedFetchOne(
            "SELECT name, remove_spaces, split_each_char,
                regexp_split_sentences, exceptions_split_sentences,
                regexp_word_characters
            FROM languages WHERE id = ?"
            . UserScopedQuery::forTablePrepared('languages', $bindings),
            $bindings
        );

        if ($langSettings === null) {
            return [
                'sentences' => 0,
                'words' => 0,
                'unknownPercent' => 100.0,
                'preview' => 'Language not found'
            ];
        }

        // Parse text (preview only, no save)
        $result = TextParsing::checkText($text, $languageId);

        return [
            'sentences' => $result['sentences'] ?? 0,
            'words' => $result['words'] ?? 0,
            'unknownPercent' => $result['unknownPercent'] ?? 100.0,
            'preview' => $result['preview'] ?? ''
        ];
    }

    /**
     * Validate text length (max 65000 bytes for MySQL TEXT column).
     *
     * @param string $text Text to validate
     *
     * @return bool True if valid, false if too long
     */
    public function validateTextLength(string $text): bool
    {
        return strlen($text) <= 65000;
    }

    /**
     * Get text length info.
     *
     * @param string $text Text content
     *
     * @return array{bytes: int, characters: int, words: int, valid: bool}
     */
    public function getTextLengthInfo(string $text): array
    {
        $bytes = strlen($text);
        $characters = mb_strlen($text, 'UTF-8');
        $words = str_word_count($text);

        return [
            'bytes' => $bytes,
            'characters' => $characters,
            'words' => $words,
            'valid' => $bytes <= 65000
        ];
    }

    /**
     * Set term sentences for words from texts.
     *
     * Links words to sentences they appear in.
     *
     * @param array $textIds    Array of text IDs
     * @param bool  $activeOnly Only update active (non-well-known) words
     *
     * @return int Number of terms updated
     */
    public function setTermSentences(array $textIds, bool $activeOnly = false): int
    {
        if (empty($textIds)) {
            return 0;
        }

        /**
 * @var array<int, int> $ids
*/
        $ids = array_values(array_map('intval', $textIds));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Get words from texts
        $sql = "SELECT DISTINCT word_occurrences.word_id, word_occurrences.sentence_id
            FROM word_occurrences, sentences, texts
            WHERE word_occurrences.text_id IN ({$placeholders})
            AND word_occurrences.sentence_id = sentences.id
            AND word_occurrences.text_id = texts.id
            AND word_occurrences.word_id > 0"
            . UserScopedQuery::forTablePrepared('texts', $ids, 'texts');

        if ($activeOnly) {
            $sql = "SELECT DISTINCT word_occurrences.word_id, word_occurrences.sentence_id
                FROM word_occurrences, sentences, texts, words
                WHERE word_occurrences.text_id IN ({$placeholders})
                AND word_occurrences.sentence_id = sentences.id
                AND word_occurrences.text_id = texts.id
                AND word_occurrences.word_id = words.id
                AND words.status < 98
                AND word_occurrences.word_id > 0"
                . UserScopedQuery::forTablePrepared('texts', $ids, 'texts')
                . UserScopedQuery::forTablePrepared('words', $ids, 'words');
        }

        $rows = Connection::preparedFetchAll($sql, $ids);
        $count = 0;

        foreach ($rows as $row) {
            $bindings = [(int) $row['sentence_id'], (int) $row['word_id']];
            Connection::preparedExecute(
                "UPDATE words SET sentence = ? WHERE id = ?"
                . UserScopedQuery::forTablePrepared('words', $bindings),
                $bindings
            );
            $count++;
        }

        return $count;
    }
}
