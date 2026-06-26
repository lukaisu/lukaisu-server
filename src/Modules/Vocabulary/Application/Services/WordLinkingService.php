<?php

/**
 * Word Linking Service - Manages word-to-text-item relationships
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

use Lukaisu\Shared\Infrastructure\Database\Connection;

/**
 * Service for managing word-to-text-item relationships.
 *
 * Handles:
 * - Linking words to text items after creation
 * - Bulk linking operations
 * - Retrieving term data from text items
 */
class WordLinkingService
{
    /**
     * Get term data from a text item at a specific position.
     *
     * @param int $textId Text ID
     * @param int $ord    Word order/position
     *
     * @return array|null Term data with text and language_id
     */
    public function getTermFromTextItem(int $textId, int $ord): ?array
    {
        // word_occurrences inherits user context via text_id -> texts FK
        return Connection::preparedFetchOne(
            "SELECT text, language_id FROM word_occurrences
             WHERE text_id = ? AND word_count = 1 AND position = ?",
            [$textId, $ord]
        );
    }

    /**
     * Link word to text items after creation.
     *
     * @param int    $wordId Word ID
     * @param int    $langId Language ID
     * @param string $textlc Lowercase text
     *
     * @return void
     */
    public function linkToTextItems(int $wordId, int $langId, string $textlc): void
    {
        // word_occurrences inherits user context via text_id -> texts FK
        Connection::preparedExecute(
            "UPDATE word_occurrences SET word_id = ?
             WHERE language_id = ? AND LOWER(text) = ?",
            [$wordId, $langId, $textlc]
        );
    }

    /**
     * Link all unlinked text items to their corresponding words.
     *
     * @return void
     */
    public function linkAllTextItems(): void
    {
        // words has user_id - user scope auto-applied
        // word_occurrences inherits user context via text_id -> texts FK
        Connection::execute(
            "UPDATE words
             JOIN word_occurrences
             ON word_id IS NULL AND LOWER(text) = text_lc AND language_id = language_id
             SET word_id = id"
        );
    }

    /**
     * Get word text at a specific position in text.
     *
     * @param int $textId Text ID
     * @param int $ord    Position in text
     *
     * @return string|null Word text or null if not found
     */
    public function getWordAtPosition(int $textId, int $ord): ?string
    {
        // word_occurrences inherits user context via text_id -> texts FK
        /** @var string|null $word */
        $word = Connection::preparedFetchValue(
            "SELECT text
             FROM word_occurrences
             WHERE word_count = 1 AND text_id = ? AND position = ?",
            [$textId, $ord],
            'text'
        );
        return $word;
    }

    /**
     * Link newly created words to text items.
     *
     * Links words with ID greater than maxWoId to their corresponding text items.
     *
     * @param int $maxWoId Maximum word ID before bulk insert
     *
     * @return void
     */
    public function linkNewWordsToTextItems(int $maxWoId): void
    {
        // word_occurrences inherits user context via text_id -> texts FK
        // words has user_id - user scope auto-applied
        Connection::preparedExecute(
            "UPDATE word_occurrences
             JOIN words
             ON LOWER(text) = text_lc AND word_count = 1 AND language_id = language_id AND id > ?
             SET word_id = id",
            [$maxWoId]
        );
    }
}
