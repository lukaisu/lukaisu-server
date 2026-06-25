<?php

/**
 * Create Language Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Language\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Language\Application\UseCases;

use Lukaisu\Shared\Infrastructure\Http\InputValidator;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;

/**
 * Use case for creating a new language.
 *
 * @since 3.0.0
 */
class CreateLanguage
{
    /**
     * Create a new language from request data.
     *
     * @return array{success: bool, id: int}
     */
    public function execute(): array
    {
        $data = $this->getLanguageDataFromRequest();

        // Check if there's an empty language record to reuse
        $row = QueryBuilder::table('languages')
            ->selectRaw('MIN(id) AS min_id')
            ->where('name', '=', '')
            ->firstPrepared();
        $existingId = isset($row['min_id']) && is_numeric($row['min_id']) ? (int)$row['min_id'] : null;

        $this->buildLanguageSql($data, $existingId);

        if ($existingId !== null) {
            return ['success' => true, 'id' => $existingId];
        }

        $row = QueryBuilder::table('languages')
            ->selectRaw('MAX(id) AS max_id')
            ->firstPrepared();
        $newId = isset($row['max_id']) && is_numeric($row['max_id']) ? (int)$row['max_id'] : 0;

        return ['success' => true, 'id' => $newId];
    }

    /**
     * Create a new language from data array (API-friendly version).
     *
     * @param array<string, mixed> $data Language data (camelCase keys)
     *
     * @return int Created language ID, or 0 on failure
     */
    public function createFromData(array $data): int
    {
        $normalizedData = $this->normalizeLanguageData($data);

        // Check if there's an empty language record to reuse
        $row = QueryBuilder::table('languages')
            ->selectRaw('MIN(id) AS min_id')
            ->where('name', '=', '')
            ->firstPrepared();
        $existingId = isset($row['min_id']) && is_numeric($row['min_id']) ? (int)$row['min_id'] : null;

        $this->buildLanguageSql($normalizedData, $existingId);

        if ($existingId !== null) {
            return $existingId;
        }

        $row = QueryBuilder::table('languages')
            ->selectRaw('MAX(id) AS max_id')
            ->firstPrepared();
        return isset($row['max_id']) && is_numeric($row['max_id']) ? (int)$row['max_id'] : 0;
    }

    /**
     * Get language data from request using InputValidator.
     *
     * @return array<string, string|int|bool|null>
     */
    public function getLanguageDataFromRequest(): array
    {
        return [
            'name' => InputValidator::getString('name'),
            'dict1_uri' => InputValidator::getString('dict1_uri'),
            'dict2_uri' => InputValidator::getString('dict2_uri'),
            'google_translate_uri' => InputValidator::getString('google_translate_uri'),
            'dict1_popup' => InputValidator::has('dict1_popup'),
            'dict2_popup' => InputValidator::has('dict2_popup'),
            'google_translate_popup' => InputValidator::has('google_translate_popup'),
            'source_lang' => InputValidator::getString('source_lang') ?: null,
            'target_lang' => InputValidator::getString('target_lang') ?: null,
            'export_template' => InputValidator::getString('export_template'),
            'text_size' => InputValidator::getString('text_size', '100'),
            'character_substitutions' => InputValidator::getString('character_substitutions', '', false),
            'regexp_split_sentences' => InputValidator::getString('regexp_split_sentences'),
            'exceptions_split_sentences' => InputValidator::getString('exceptions_split_sentences', '', false),
            'regexp_word_characters' => InputValidator::getString('regexp_word_characters'),
            'parser_type' => InputValidator::getString('parser_type') ?: null,
            'remove_spaces' => InputValidator::has('remove_spaces'),
            'split_each_char' => InputValidator::has('split_each_char'),
            'right_to_left' => InputValidator::has('right_to_left'),
            'tts_voice_api' => InputValidator::getString('tts_voice_api'),
            'show_romanization' => InputValidator::has('show_romanization'),
            'local_dict_mode' => (int) InputValidator::getString('local_dict_mode', '0'),
        ];
    }

    /**
     * Normalize language data from API request to database fields.
     *
     * @param array<string, mixed> $data API request data (camelCase keys)
     *
     * @return array<string, bool|int|null|string> Normalized data (LgXxx keys)
     */
    private function normalizeLanguageData(array $data): array
    {
        $getStr = static fn(string $key, string $default = ''): string =>
            isset($data[$key]) && is_string($data[$key]) ? $data[$key] : $default;

        $getStrOrNull = static fn(string $key): ?string =>
            isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;

        return [
            'name' => $getStr('name'),
            'dict1_uri' => $getStr('dict1Uri'),
            'dict2_uri' => $getStr('dict2Uri'),
            'google_translate_uri' => $getStr('translatorUri'),
            'dict1_popup' => !empty($data['dict1PopUp']),
            'dict2_popup' => !empty($data['dict2PopUp']),
            'google_translate_popup' => !empty($data['translatorPopUp']),
            'source_lang' => $getStrOrNull('sourceLang'),
            'target_lang' => $getStrOrNull('targetLang'),
            'export_template' => $getStr('exportTemplate'),
            'text_size' => (string)($data['textSize'] ?? '100'),
            'character_substitutions' => $getStr('characterSubstitutions'),
            'regexp_split_sentences' => $getStr('regexpSplitSentences', '.!?'),
            'exceptions_split_sentences' => $getStr('exceptionsSplitSentences'),
            'regexp_word_characters' => $getStr('regexpWordCharacters', 'a-zA-Z'),
            'parser_type' => $getStrOrNull('parserType'),
            'remove_spaces' => !empty($data['removeSpaces']),
            'split_each_char' => !empty($data['splitEachChar']),
            'right_to_left' => !empty($data['rightToLeft']),
            'tts_voice_api' => $getStr('ttsVoiceApi'),
            'show_romanization' => !empty($data['showRomanization']),
            'local_dict_mode' => (int)($data['localDictMode'] ?? 0),
        ];
    }

    /**
     * Convert empty strings to null.
     *
     * @param string|null $value Value to convert
     *
     * @return string|null Trimmed value or null if empty
     */
    private function emptyToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Get a string value from data array, defaulting to empty string.
     *
     * @param array<string, string|int|bool|null> $data Data array
     * @param string $key Key to retrieve
     *
     * @return string Value as string
     */
    private function getString(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        return is_string($value) ? $value : (string)$value;
    }

    /**
     * Get a string or null value from data array.
     *
     * @param array<string, string|int|bool|null> $data Data array
     * @param string $key Key to retrieve
     *
     * @return string|null Value as string or null
     */
    private function getStringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        return is_string($value) ? $value : (string)$value;
    }

    /**
     * Build SQL and execute insert or update for a language.
     *
     * @param array<string, string|int|bool|null> $data Language data
     * @param int|null $id   Language ID for update, null for insert
     *
     * @return void
     */
    private function buildLanguageSql(array $data, ?int $id = null): void
    {
        $columns = [
            'name', 'dict1_uri', 'dict2_uri', 'google_translate_uri',
            'dict1_popup', 'dict2_popup', 'google_translate_popup',
            'source_lang', 'target_lang',
            'export_template', 'text_size', 'character_substitutions',
            'regexp_split_sentences', 'exceptions_split_sentences',
            'regexp_word_characters', 'parser_type', 'remove_spaces', 'split_each_char',
            'right_to_left', 'tts_voice_api', 'show_romanization', 'local_dict_mode'
        ];

        $params = [
            $this->emptyToNull($this->getString($data, "name")),
            $this->emptyToNull($this->getString($data, "dict1_uri")),
            $this->emptyToNull($this->getString($data, "dict2_uri")),
            $this->emptyToNull($this->getString($data, "google_translate_uri")),
            (int)($data["dict1_popup"] ?? false),
            (int)($data["dict2_popup"] ?? false),
            (int)($data["google_translate_popup"] ?? false),
            $this->emptyToNull($this->getStringOrNull($data, "source_lang")),
            $this->emptyToNull($this->getStringOrNull($data, "target_lang")),
            $this->emptyToNull($this->getString($data, "export_template")),
            $this->emptyToNull($this->getString($data, "text_size")),
            $this->getString($data, "character_substitutions"),
            $this->emptyToNull($this->getString($data, "regexp_split_sentences")),
            $this->getString($data, "exceptions_split_sentences"),
            $this->emptyToNull($this->getString($data, "regexp_word_characters")),
            $this->getStringOrNull($data, "parser_type"),
            (int)($data["remove_spaces"] ?? false),
            (int)($data["split_each_char"] ?? false),
            (int)($data["right_to_left"] ?? false),
            $this->getString($data, "tts_voice_api"),
            (int)($data["show_romanization"] ?? false),
            (int)($data["local_dict_mode"] ?? 0),
        ];

        $insertData = array_combine($columns, $params);

        if ($id === null) {
            QueryBuilder::table('languages')->insertPrepared($insertData);
        } else {
            QueryBuilder::table('languages')
                ->where('id', '=', $id)
                ->updatePrepared($insertData);
        }
    }
}
