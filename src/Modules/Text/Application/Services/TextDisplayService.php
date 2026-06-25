<?php

/**
 * Text Display Service - Business logic for displaying annotated texts
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Application\Services;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Settings;

/**
 * Service class for displaying annotated texts.
 *
 * Handles data retrieval for improved text display views.
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class TextDisplayService
{
    /**
     * Get header data for a text.
     *
     * @param int $textId Text ID
     *
     * @return array{title: string, audio: string, sourceUri: string|null}|null
     */
    public function getHeaderData(int $textId): ?array
    {
        $record = QueryBuilder::table('texts')
            ->select(['title', 'audio_uri', 'source_uri'])
            ->where('id', '=', $textId)
            ->firstPrepared();

        if ($record === null) {
            return null;
        }

        $audio = '';
        if (isset($record['audio_uri'])) {
            $audio = trim((string) $record['audio_uri']);
        }

        Settings::savePerUser('currenttext', $textId);

        return [
            'title' => (string) $record['title'],
            'audio' => $audio,
            'sourceUri' => $record['source_uri'] !== null ? (string) $record['source_uri'] : null
        ];
    }

    /**
     * Get text display settings (text size, RTL).
     *
     * @param int $textId Text ID
     *
     * @return array{textSize: int, rtlScript: bool}|null
     */
    public function getTextDisplaySettings(int $textId): ?array
    {
        $record = QueryBuilder::table('texts')
            ->select(['LgTextSize', 'LgRightToLeft'])
            ->join('languages', 'LgID', '=', 'language_id')
            ->where('id', '=', $textId)
            ->firstPrepared();

        if ($record === null) {
            return null;
        }

        return [
            'textSize' => (int) $record['LgTextSize'],
            'rtlScript' => (bool) $record['LgRightToLeft']
        ];
    }

    /**
     * Get annotated text content.
     *
     * @param int $textId Text ID
     *
     * @return string Annotated text
     */
    public function getAnnotatedText(int $textId): string
    {
        $record = QueryBuilder::table('texts')
            ->select(['annotated_text'])
            ->where('id', '=', $textId)
            ->firstPrepared();

        return $record !== null ? (string) $record['annotated_text'] : '';
    }

    /**
     * Get audio URI for a text.
     *
     * @param int $textId Text ID
     *
     * @return string|null Audio URI or null
     */
    public function getAudioUri(int $textId): ?string
    {
        $record = QueryBuilder::table('texts')
            ->select(['audio_uri'])
            ->where('id', '=', $textId)
            ->firstPrepared();

        return $record !== null && $record['audio_uri'] !== null
            ? (string) $record['audio_uri']
            : null;
    }

    /**
     * Get word romanization by ID.
     *
     * @param int $wordId Word ID
     *
     * @return string Romanization or empty string
     */
    public function getWordRomanization(int $wordId): string
    {
        $record = QueryBuilder::table('words')
            ->select(['romanization'])
            ->where('id', '=', $wordId)
            ->firstPrepared();

        return $record !== null && $record['romanization'] !== null
            ? (string) $record['romanization']
            : '';
    }

    /**
     * Parse annotation item into display data.
     *
     * @param string $item Annotation item (tab-separated values)
     *
     * @return array{type: int, text: string, trans: string, rom: string}|null
     */
    public function parseAnnotationItem(string $item): ?array
    {
        $vals = preg_split('/[\t]/u', $item);

        if (!is_array($vals) || count($vals) < 2) {
            return null;
        }

        $type = (int) $vals[0];
        $text = $vals[1];
        $trans = '';
        $rom = '';

        if ($type > -1) {
            // Word with potential annotation
            if (count($vals) > 2 && $vals[2] !== '') {
                $wid = (int) $vals[2];
                $rom = $this->getWordRomanization($wid);
            }
            if (count($vals) > 3) {
                $trans = $vals[3];
            }
            if ($trans === '*') {
                $trans = $text . " "; // <- U+200A HAIR SPACE
            }
        }

        return [
            'type' => $type,
            'text' => $text,
            'trans' => $trans,
            'rom' => $rom
        ];
    }

    /**
     * Parse all annotations from annotated text.
     *
     * @param string $annotatedText Raw annotated text
     *
     * @return array Array of parsed annotation items
     */
    public function parseAnnotations(string $annotatedText): array
    {
        $items = preg_split('/[\n]/u', $annotatedText);
        if ($items === false) {
            return [];
        }
        $parsed = [];

        foreach ($items as $item) {
            $parsedItem = $this->parseAnnotationItem($item);
            if ($parsedItem !== null) {
                $parsed[] = $parsedItem;
            }
        }

        return $parsed;
    }

    /**
     * Save current text ID to settings.
     *
     * @param int $textId Text ID
     *
     * @return void
     */
    public function saveCurrentText(int $textId): void
    {
        Settings::savePerUser('currenttext', $textId);
    }
}
