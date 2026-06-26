<?php

/**
 * Update Text Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Application\UseCases;

use Lukaisu\Modules\Text\Domain\AudioUriValidator;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\Maintenance;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\TextParsing;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Use case for updating texts.
 *
 * Handles updates to both active and archived texts, including
 * reparsing when text content changes.
 */
class UpdateText
{
    /**
     * Update an active text.
     *
     * @param int    $textId     Text ID
     * @param int    $languageId Language ID
     * @param string $title      Title
     * @param string $text       Text content
     * @param string $audioUri   Audio URI
     * @param string $sourceUri  Source URI
     *
     * @return array{updated: bool, reparsed: bool}
     */
    public function execute(
        int $textId,
        int $languageId,
        string $title,
        string $text,
        string $audioUri,
        string $sourceUri
    ): array {
        // Remove soft hyphens
        $text = $this->removeSoftHyphens($text);

        // Check if text content changed + fetch the prior audio_uri so
        // the validator can grandfather unchanged values.
        $bindings1 = [$textId];
        /** @var array{text: ?string, audio_uri: ?string}|null $existing */
        $existing = Connection::preparedFetchOne(
            "SELECT text, audio_uri FROM texts WHERE id = ?"
            . UserScopedQuery::forTablePrepared('texts', $bindings1),
            $bindings1
        );
        $oldText = $existing['text'] ?? null;
        $previousAudioUri = $existing['audio_uri'] ?? null;
        $textChanged = $text !== $oldText;

        $audioUri = AudioUriValidator::validate($audioUri, $previousAudioUri);

        // Update text
        $bindings2 = [$languageId, $title, $text, $audioUri, $sourceUri, $textId];
        $affected = Connection::preparedExecute(
            "UPDATE texts SET
                language_id = ?, title = ?, text = ?, audio_uri = ?, source_uri = ?
            WHERE id = ?"
            . UserScopedQuery::forTablePrepared('texts', $bindings2),
            $bindings2
        );

        $updated = $affected > 0;
        $reparsed = false;

        // Reparse if text changed
        if ($updated && $textChanged) {
            $this->reparseText($textId, $languageId, $text);
            $reparsed = true;
        }

        return ['updated' => $updated, 'reparsed' => $reparsed];
    }

    /**
     * Save text and reparse (alias for execute with reparse).
     *
     * @param int    $textId     Text ID
     * @param int    $languageId Language ID
     * @param string $title      Title
     * @param string $text       Text content
     * @param string $audioUri   Audio URI
     * @param string $sourceUri  Source URI
     *
     * @return string Result message
     */
    public function saveTextAndReparse(
        int $textId,
        int $languageId,
        string $title,
        string $text,
        string $audioUri,
        string $sourceUri
    ): string {
        $result = $this->execute($textId, $languageId, $title, $text, $audioUri, $sourceUri);
        return self::formatUpdateMessage($result['updated'], $result['reparsed']);
    }

    /**
     * Format update result as a user-facing message.
     *
     * @param bool $updated  Whether the text was updated
     * @param bool $reparsed Whether the text was reparsed
     *
     * @return string Formatted message
     */
    public static function formatUpdateMessage(bool $updated, bool $reparsed): string
    {
        if (!$updated) {
            return 'No changes';
        }
        return $reparsed ? 'Updated and reparsed' : 'Updated';
    }

    /**
     * Update an archived text.
     *
     * @param int    $textId     Archived text ID
     * @param int    $languageId Language ID
     * @param string $title      Title
     * @param string $text       Text content
     * @param string $audioUri   Audio URI
     * @param string $sourceUri  Source URI
     *
     * @return int Number of rows affected
     */
    public function updateArchivedText(
        int $textId,
        int $languageId,
        string $title,
        string $text,
        string $audioUri,
        string $sourceUri
    ): int {
        // Check if text content changed + fetch the prior audio_uri so
        // the validator can grandfather unchanged values.
        $bindings1 = [$textId];
        /** @var array{text: ?string, audio_uri: ?string}|null $existing */
        $existing = Connection::preparedFetchOne(
            "SELECT text, audio_uri FROM texts WHERE id = ? AND archived_at IS NOT NULL"
            . UserScopedQuery::forTablePrepared('texts', $bindings1),
            $bindings1
        );
        $oldText = $existing['text'] ?? null;
        $previousAudioUri = $existing['audio_uri'] ?? null;
        $textsdiffer = $text !== $oldText;

        $audioUri = AudioUriValidator::validate($audioUri, $previousAudioUri);

        $bindings2 = [$languageId, $title, $text, $audioUri, $sourceUri, $textId];
        $affected = Connection::preparedExecute(
            "UPDATE texts SET
                language_id = ?, title = ?, text = ?, audio_uri = ?, source_uri = ?
             WHERE id = ? AND archived_at IS NOT NULL"
            . UserScopedQuery::forTablePrepared('texts', $bindings2),
            $bindings2
        );

        // Clear annotation if text changed
        if ($affected > 0 && $textsdiffer) {
            $bindings3 = [$textId];
            Connection::preparedExecute(
                "UPDATE texts SET annotated_text = '' WHERE id = ? AND archived_at IS NOT NULL"
                . UserScopedQuery::forTablePrepared('texts', $bindings3),
                $bindings3
            );
        }

        return $affected;
    }

    /**
     * Format archived text update result as a user-facing message.
     *
     * @param int $affectedCount Number of rows affected
     *
     * @return string Formatted message
     */
    public static function formatArchivedUpdateMessage(int $affectedCount): string
    {
        return "Updated: {$affectedCount}";
    }

    /**
     * Rebuild/reparse multiple texts.
     *
     * @param array $textIds Array of text IDs
     *
     * @return int Number of texts rebuilt
     */
    public function rebuildTexts(array $textIds): int
    {
        if (empty($textIds)) {
            return 0;
        }

        $count = 0;
        /**
 * @var array<int, int> $ids
*/
        $ids = array_values(array_map('intval', $textIds));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $records = Connection::preparedFetchAll(
            "SELECT id, language_id, text FROM texts WHERE id IN ({$placeholders})"
            . UserScopedQuery::forTablePrepared('texts', $ids),
            $ids
        );

        foreach ($records as $record) {
            $this->reparseText(
                (int) $record['id'],
                (int) $record['language_id'],
                (string) $record['text']
            );
            $count++;
        }

        return $count;
    }

    /**
     * Format rebuild result as a user-facing message.
     *
     * @param int $count Number of texts rebuilt
     *
     * @return string Formatted message
     */
    public static function formatRebuildMessage(int $count): string
    {
        return "Rebuilt Text(s): {$count}";
    }

    /**
     * Reparse a text (delete old parsed data and parse again).
     *
     * @param int    $textId     Text ID
     * @param int    $languageId Language ID
     * @param string $text       Text content
     */
    private function reparseText(int $textId, int $languageId, string $text): void
    {
        // Delete old parsed data
        QueryBuilder::table('word_occurrences')
            ->where('text_id', '=', $textId)
            ->delete();
        QueryBuilder::table('sentences')
            ->where('text_id', '=', $textId)
            ->delete();

        Maintenance::adjustAutoIncrement('sentences', 'id');

        // Clear annotation
        $bindings = [$textId];
        Connection::preparedExecute(
            "UPDATE texts SET annotated_text = '' WHERE id = ?"
            . UserScopedQuery::forTablePrepared('texts', $bindings),
            $bindings
        );

        // Parse again
        TextParsing::parseAndSave($text, $languageId, $textId);
    }

    /**
     * Remove soft hyphens from text.
     *
     * @param string $text Text to clean
     *
     * @return string Cleaned text
     */
    private function removeSoftHyphens(string $text): string
    {
        return str_replace("\xC2\xAD", "", $text);
    }
}
