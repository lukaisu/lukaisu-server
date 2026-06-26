<?php

/**
 * Update Language Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Language\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Language\Application\UseCases;

use Lukaisu\Shared\Infrastructure\Http\InputValidator;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;

/**
 * Use case for updating an existing language.
 */
class UpdateLanguage
{
    private ReparseLanguageTexts $reparseUseCase;

    /**
     * @param ReparseLanguageTexts|null $reparseUseCase Reparse use case
     */
    public function __construct(?ReparseLanguageTexts $reparseUseCase = null)
    {
        $this->reparseUseCase = $reparseUseCase ?? new ReparseLanguageTexts();
    }

    /**
     * Update an existing language from request data.
     *
     * @param int $id Language ID
     *
     * @return array{success: bool, reparsed: ?int, error: ?string}
     */
    public function execute(int $id): array
    {
        $data = $this->getLanguageDataFromRequest();

        // Get old values for comparison
        $records = QueryBuilder::table('languages')
            ->where('id', '=', $id)
            ->getPrepared();
        if (empty($records)) {
            return ['success' => false, 'reparsed' => null, 'error' => 'Cannot access language data'];
        }
        $record = $records[0];

        // Check if reparsing is needed
        $needReParse = $this->needsReparsing($data, $record);

        // Update language
        $this->buildLanguageSql($data, $id);

        $reparseCount = null;
        if ($needReParse) {
            $reparseCount = $this->reparseUseCase->reparseTexts($id);
        }

        return ['success' => true, 'reparsed' => $reparseCount, 'error' => null];
    }

    /**
     * Update an existing language from data array (API-friendly version).
     *
     * @param int   $id   Language ID
     * @param array<string, mixed> $data Language data (camelCase keys)
     *
     * @return array{success: bool, reparsed: int, message: string}
     */
    public function updateFromData(int $id, array $data): array
    {
        $normalizedData = $this->normalizeLanguageData($data);

        // Get old values for comparison
        $records = QueryBuilder::table('languages')
            ->where('id', '=', $id)
            ->getPrepared();

        if (empty($records)) {
            return ['success' => false, 'reparsed' => 0, 'message' => __('language.errors.language_not_found')];
        }

        $record = $records[0];

        // Check if reparsing is needed
        $needReParse = $this->needsReparsing($normalizedData, $record);

        // Update language
        $this->buildLanguageSql($normalizedData, $id);

        $reparsedCount = 0;
        if ($needReParse) {
            $reparsedCount = $this->reparseUseCase->reparseTexts($id);
        }

        return [
            'success' => true,
            'reparsed' => $reparsedCount,
            'message' => $needReParse ? 'Updated and reparsed' : 'Updated'
        ];
    }

    /**
     * Get language data from request using InputValidator.
     *
     * @return array<string, string|int|bool|null>
     */
    private function getLanguageDataFromRequest(): array
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
     * Check if language changes require reparsing texts.
     *
     * @param array<string, string|int|bool|null> $newData   New language data
     * @param array<string, mixed> $oldRecord Old language data
     *
     * @return bool
     */
    private function needsReparsing(array $newData, array $oldRecord): bool
    {
        $getStr = static fn(array $arr, string $key): string =>
            isset($arr[$key]) && is_string($arr[$key]) ? $arr[$key] : '';

        return (
            $getStr($newData, "character_substitutions")
            != $getStr($oldRecord, 'character_substitutions')
        ) || (
            trim($getStr($newData, "regexp_split_sentences")) !=
            trim($getStr($oldRecord, 'regexp_split_sentences'))
        ) || (
            $getStr($newData, "exceptions_split_sentences")
            != $getStr($oldRecord, 'exceptions_split_sentences')
        ) || (
            trim($getStr($newData, "regexp_word_characters")) !=
            trim($getStr($oldRecord, 'regexp_word_characters'))
        ) || ((int)($newData["remove_spaces"] ?? 0) != (int)($oldRecord['remove_spaces'] ?? 0)) ||
        ((int)($newData["split_each_char"] ?? 0) != (int)($oldRecord['split_each_char'] ?? 0)) ||
        (($newData["parser_type"] ?? null) != ($oldRecord['parser_type'] ?? null));
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
     * Build SQL and execute update for a language.
     *
     * @param array<string, string|int|bool|null> $data Language data
     * @param int   $id   Language ID
     *
     * @return void
     */
    private function buildLanguageSql(array $data, int $id): void
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

        $updateData = array_combine($columns, $params);

        QueryBuilder::table('languages')
            ->where('id', '=', $id)
            ->updatePrepared($updateData);
    }
}
