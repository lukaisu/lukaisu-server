<?php

/**
 * Archive Text Use Case
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
use Lukaisu\Shared\Infrastructure\Database\DB;
use Lukaisu\Shared\Infrastructure\Database\Maintenance;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\TextParsing;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Use case for archiving and unarchiving texts.
 *
 * Texts are archived by setting archived_at to the current timestamp.
 * Unarchiving sets archived_at back to NULL.
 *
 * @since 3.0.0
 */
class ArchiveText
{
    /**
     * Archive an active text.
     *
     * Sets archived_at to current timestamp and deletes parsed data.
     *
     * @param int $textId Text ID
     *
     * @return array{sentences: int, textItems: int, archived: int} Counts
     */
    public function execute(int $textId): array
    {
        // Delete parsed data
        $count3 = QueryBuilder::table('word_occurrences')
            ->where('text_id', '=', $textId)
            ->delete();
        $count2 = QueryBuilder::table('sentences')
            ->where('text_id', '=', $textId)
            ->delete();

        // Mark as archived
        $bindings = [$textId];
        $archived = Connection::preparedExecute(
            "UPDATE texts SET archived_at = NOW(), position = 0, audio_position = 0
            WHERE id = ? AND archived_at IS NULL"
            . UserScopedQuery::forTablePrepared('texts', $bindings),
            $bindings
        );

        Maintenance::adjustAutoIncrement('sentences', 'id');

        return ['sentences' => $count2, 'textItems' => $count3, 'archived' => $archived];
    }

    /**
     * Archive multiple texts.
     *
     * @param array $textIds Array of text IDs
     *
     * @return array{count: int} Count of archived texts
     */
    public function archiveMultiple(array $textIds): array
    {
        if (empty($textIds)) {
            return ['count' => 0];
        }

        $ids = array_map('intval', $textIds);
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($ids as $textId) {
                // Delete parsed data
                QueryBuilder::table('word_occurrences')
                    ->where('text_id', '=', $textId)
                    ->delete();
                QueryBuilder::table('sentences')
                    ->where('text_id', '=', $textId)
                    ->delete();

                // Mark as archived
                $bindings = [$textId];
                $count += Connection::preparedExecute(
                    "UPDATE texts SET archived_at = NOW(), position = 0, audio_position = 0
                    WHERE id = ? AND archived_at IS NULL"
                    . UserScopedQuery::forTablePrepared('texts', $bindings),
                    $bindings
                );
            }

            Maintenance::adjustAutoIncrement('sentences', 'id');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        return ['count' => $count];
    }

    /**
     * Unarchive a text (restore from archived state).
     *
     * Sets archived_at to NULL and re-parses the text.
     *
     * @param int $textId Text ID (archived)
     *
     * @return array{success: bool, textId: ?int, unarchived: int, sentences: int, textItems: int, error: ?string}
     */
    public function unarchive(int $textId): array
    {
        // Get language ID first
        $bindings = [$textId];
        $text = Connection::preparedFetchOne(
            "SELECT language_id, text FROM texts
            WHERE id = ? AND archived_at IS NOT NULL"
            . UserScopedQuery::forTablePrepared('texts', $bindings),
            $bindings
        );

        if ($text === null) {
            return [
                'success' => false,
                'textId' => null,
                'unarchived' => 0,
                'sentences' => 0,
                'textItems' => 0,
                'error' => 'Archived text not found'
            ];
        }

        // Unarchive
        $bindings2 = [$textId];
        $unarchived = Connection::preparedExecute(
            "UPDATE texts SET archived_at = NULL
            WHERE id = ? AND archived_at IS NOT NULL"
            . UserScopedQuery::forTablePrepared('texts', $bindings2),
            $bindings2
        );

        // Re-parse the text
        TextParsing::parseAndSave((string)($text['text'] ?? ''), (int) $text['language_id'], $textId);

        // Get statistics
        $bindings3 = [$textId];
        $sentenceCount = (int)Connection::preparedFetchValue(
            "SELECT COUNT(*) AS cnt FROM sentences WHERE text_id = ?"
            . UserScopedQuery::forTablePrepared('sentences', $bindings3, '', 'texts'),
            $bindings3,
            'cnt'
        );
        $bindings4 = [$textId];
        $itemCount = (int)Connection::preparedFetchValue(
            "SELECT COUNT(*) AS cnt FROM word_occurrences WHERE text_id = ?"
            . UserScopedQuery::forTablePrepared('word_occurrences', $bindings4, '', 'texts'),
            $bindings4,
            'cnt'
        );

        return [
            'success' => true,
            'textId' => $textId,
            'unarchived' => $unarchived,
            'sentences' => $sentenceCount,
            'textItems' => $itemCount,
            'error' => null
        ];
    }

    /**
     * Unarchive multiple texts.
     *
     * @param array $textIds Array of archived text IDs
     *
     * @return array{count: int} Count of unarchived texts
     */
    public function unarchiveMultiple(array $textIds): array
    {
        if (empty($textIds)) {
            return ['count' => 0];
        }

        $ids = array_map('intval', $textIds);
        $count = 0;

        foreach ($ids as $textId) {
            $result = $this->unarchive($textId);
            if ($result['success']) {
                $count++;
            }
        }

        return ['count' => $count];
    }
}
