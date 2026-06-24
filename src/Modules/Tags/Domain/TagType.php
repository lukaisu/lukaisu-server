<?php

/**
 * Tag Type Enum
 *
 * Discriminates between term tags and text tags.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Tags\Domain
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Tags\Domain;

/**
 * Enum representing the type of tag (term or text).
 *
 * Each type has its own database table and column naming conventions.
 *
 * @since 3.0.0
 */
enum TagType: string
{
    case TERM = 'term';
    case TEXT = 'text';

    /**
     * Get the main table name for this tag type.
     *
     * @return string Table name ('tags' or 'text_tags')
     */
    public function tableName(): string
    {
        return match ($this) {
            self::TERM => 'tags',
            self::TEXT => 'text_tags',
        };
    }

    /**
     * Get the ID column name.
     *
     * Both tag tables share identical column names after the snake_case rename, so
     * the column accessors return literals (the legacy Tg / T2 prefixes are gone).
     *
     * @return string Column name ('id')
     */
    public function idColumn(): string
    {
        return 'id';
    }

    /**
     * Get the text column name.
     *
     * @return string Column name ('text')
     */
    public function textColumn(): string
    {
        return 'text';
    }

    /**
     * Get the comment column name.
     *
     * @return string Column name ('comment')
     */
    public function commentColumn(): string
    {
        return 'comment';
    }

    /**
     * Get the user ID column name.
     *
     * @return string Column name ('user_id')
     */
    public function userIdColumn(): string
    {
        return 'user_id';
    }

    /**
     * Get the primary association table for this tag type.
     *
     * For term tags, this is 'word_tag_map'.
     * For text tags, this is 'text_tag_map'.
     *
     * @return string Table name
     */
    public function associationTable(): string
    {
        return match ($this) {
            self::TERM => 'word_tag_map',
            self::TEXT => 'text_tag_map',
        };
    }

    /**
     * Get the human-readable label for this tag type.
     *
     * @return string 'Term' or 'Text'
     */
    public function label(): string
    {
        return match ($this) {
            self::TERM => 'Term',
            self::TEXT => 'Text',
        };
    }

    /**
     * Get the base URL for this tag type.
     *
     * @return string '/tags' or '/tags/text'
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::TERM => '/tags',
            self::TEXT => '/tags/text',
        };
    }

    /**
     * Get the items URL pattern for this tag type.
     *
     * @return string URL pattern for viewing tagged items
     */
    public function itemsUrlPattern(): string
    {
        return match ($this) {
            self::TERM => '/words?tag=%d',
            self::TEXT => '/texts?tag=%d',
        };
    }

    /**
     * Check if this is a term tag type.
     *
     * @return bool
     */
    public function isTerm(): bool
    {
        return $this === self::TERM;
    }

    /**
     * Check if this is a text tag type.
     *
     * @return bool
     */
    public function isText(): bool
    {
        return $this === self::TEXT;
    }
}
