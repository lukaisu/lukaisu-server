<?php

/**
 * Get Text For Reading Use Case
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
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Text\Domain\TextRepositoryInterface;

/**
 * Use case for retrieving text data for reading interface.
 *
 * Prepares all necessary data for the text reading view including
 * text content, language settings, TTS configuration, and navigation.
 *
 * @since 3.0.0
 */
class GetTextForReading
{
    private TextRepositoryInterface $textRepository;

    /**
     * Constructor.
     *
     * @param TextRepositoryInterface $textRepository Text repository
     */
    public function __construct(TextRepositoryInterface $textRepository)
    {
        $this->textRepository = $textRepository;
    }

    /**
     * Get text data for reading interface.
     *
     * @param int $textId Text ID
     *
     * @return array|null Text data for reading or null if not found
     */
    public function execute(int $textId): ?array
    {
        $bindings = [$textId];
        return Connection::preparedFetchOne(
            "SELECT languages.name, texts.language_id, texts.text, texts.title,
                texts.audio_uri, texts.source_uri, texts.audio_position
            FROM texts
            JOIN languages ON texts.language_id = languages.id
            WHERE texts.id = ?"
            . UserScopedQuery::forTablePrepared('texts', $bindings, 'texts')
            . UserScopedQuery::forTablePrepared('languages', $bindings, 'languages'),
            $bindings
        );
    }

    /**
     * Get language settings for reading display.
     *
     * @param int $languageId Language ID
     *
     * @return array|null Language settings or null if not found
     */
    public function getLanguageSettingsForReading(int $languageId): ?array
    {
        $bindings = [$languageId];
        return Connection::preparedFetchOne(
            "SELECT name, dict1_uri, dict2_uri, google_translate_uri,
                text_size, regexp_word_characters, remove_spaces, right_to_left
            FROM languages
            WHERE id = ?"
            . UserScopedQuery::forTablePrepared('languages', $bindings),
            $bindings
        );
    }

    /**
     * Get TTS voice API for a language.
     *
     * @param int $languageId Language ID
     *
     * @return string TTS voice API string or empty
     */
    public function getTtsVoiceApi(int $languageId): string
    {
        $bindings = [$languageId];
        /**
 * @var string|null $result
*/
        $result = Connection::preparedFetchValue(
            "SELECT tts_voice_api FROM languages WHERE id = ?"
            . UserScopedQuery::forTablePrepared('languages', $bindings),
            $bindings,
            'tts_voice_api'
        );
        return $result ?? '';
    }

    /**
     * Get language ID by language name.
     *
     * @param string $languageName Language name
     *
     * @return int|null Language ID or null if not found
     */
    public function getLanguageIdByName(string $languageName): ?int
    {
        $bindings = [$languageName];
        /**
 * @var int|string|null $result
*/
        $result = Connection::preparedFetchValue(
            "SELECT id FROM languages WHERE name = ?"
            . UserScopedQuery::forTablePrepared('languages', $bindings),
            $bindings,
            'id'
        );
        return $result !== null ? (int) $result : null;
    }

    /**
     * Get previous and next text IDs for navigation.
     *
     * @param int $textId     Current text ID
     * @param int $languageId Language ID
     *
     * @return array{previous: int|null, next: int|null}
     */
    public function getNavigation(int $textId, int $languageId): array
    {
        return [
            'previous' => $this->textRepository->getPreviousTextId($textId, $languageId),
            'next' => $this->textRepository->getNextTextId($textId, $languageId),
        ];
    }

    /**
     * Get Google Translate URIs for languages.
     *
     * @return array<int, string> Map of language ID to translate URI
     */
    public function getLanguageTranslateUris(): array
    {
        $bindings = [];
        $rows = Connection::preparedFetchAll(
            "SELECT id, google_translate_uri FROM languages WHERE 1=1"
            . UserScopedQuery::forTablePrepared('languages', $bindings),
            $bindings
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = (string) $row['google_translate_uri'];
        }
        return $result;
    }
}
