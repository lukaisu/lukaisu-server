<?php

/**
 * Text Creation Adapter
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Feed\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Feed\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\DB;
use Lukaisu\Shared\Infrastructure\Database\Maintenance;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\TextParsing;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Feed\Domain\TextCreationInterface;

/**
 * Adapter implementing TextCreationInterface.
 *
 * Provides text creation functionality for the Feed module by
 * using existing Lukaisu Server infrastructure (TextParsing, QueryBuilder, etc.).
 *
 * @since 3.0.0
 */
class TextCreationAdapter implements TextCreationInterface
{
    /**
     * {@inheritdoc}
     */
    public function createText(
        int $languageId,
        string $title,
        string $text,
        string $audioUri,
        string $sourceUri,
        string $tagName
    ): int {
        DB::beginTransaction();
        try {
            // Ensure tag exists - use raw SQL for INSERT IGNORE.
            // forTablePrepared appends " AND user_id = ?" which is invalid
            // after a VALUES clause; inject user_id into the column/value
            // list instead so the row is correctly scoped to this user.
            $bindings = [$tagName];
            $userScopeColumn = '';
            $userScopeValue = '';
            $userIdForInsert = UserScopedQuery::getUserIdForInsert('text_tags');
            if ($userIdForInsert !== null) {
                $userScopeColumn = ', user_id';
                $userScopeValue = ', ?';
                $bindings[] = $userIdForInsert;
            }
            $sql = "INSERT IGNORE INTO text_tags (text{$userScopeColumn}) VALUES (?{$userScopeValue})";
            Connection::preparedExecute($sql, $bindings);

            // Create the text
            $textId = QueryBuilder::table('texts')
                ->insertPrepared([
                    'language_id' => $languageId,
                    'title' => $title,
                    'text' => $text,
                    'audio_uri' => $audioUri,
                    'source_uri' => $sourceUri,
                ]);

            // Parse the text into sentences and textitems
            TextParsing::parseAndSave(
                $text,
                $languageId,
                (int) $textId
            );

            // Apply tag to the text
            $bindings = [(int) $textId, $tagName];
            $sql = "INSERT INTO text_tag_map (text_id, text_tag_id)
                 SELECT ?, id FROM text_tags
                 WHERE text = ?"
                . UserScopedQuery::forTablePrepared('text_tags', $bindings);
            Connection::preparedExecute($sql, $bindings);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        return (int) $textId;
    }

    /**
     * {@inheritdoc}
     */
    public function archiveOldTexts(string $tagName, int $maxTexts): array
    {
        // Get all text IDs with this tag
        $bindings = [$tagName];
        $sql = "SELECT text_id FROM text_tag_map
             JOIN text_tags ON text_tag_id = id
             WHERE text = ?"
            . UserScopedQuery::forTablePrepared('text_tags', $bindings);
        $rows = Connection::preparedFetchAll($sql, $bindings);

        $textIds = [];
        foreach ($rows as $row) {
            $textIds[] = (int) $row['text_id'];
        }

        $stats = ['archived' => 0, 'sentences' => 0, 'textitems' => 0];

        if (count($textIds) <= $maxTexts) {
            return $stats;
        }

        // Sort by ID (oldest first) and archive the excess
        sort($textIds, SORT_NUMERIC);
        $textsToArchive = array_slice($textIds, 0, count($textIds) - $maxTexts);

        DB::beginTransaction();
        try {
            foreach ($textsToArchive as $textId) {
                // Delete textitems
                $stats['textitems'] += QueryBuilder::table('word_occurrences')
                    ->where('text_id', '=', $textId)
                    ->delete();

                // Delete sentences
                $stats['sentences'] += QueryBuilder::table('sentences')
                    ->where('text_id', '=', $textId)
                    ->delete();

                // Archive the text (soft delete - set archived_at)
                $bindings = [$textId];
                $sql = "UPDATE texts SET archived_at = NOW(), position = 0, audio_position = 0
                        WHERE id = ? AND archived_at IS NULL"
                    . UserScopedQuery::forTablePrepared('texts', $bindings);
                $archived = Connection::preparedExecute($sql, $bindings);

                if ($archived > 0) {
                    $stats['archived']++;
                }
            }

            Maintenance::adjustAutoIncrement('sentences', 'id');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        return $stats;
    }

    /**
     * {@inheritdoc}
     */
    public function countTextsWithTag(string $tagName): int
    {
        $bindings = [$tagName];
        $sql = "SELECT COUNT(DISTINCT text_id) as cnt FROM text_tag_map
             JOIN text_tags ON text_tag_id = id
             WHERE text = ?"
            . UserScopedQuery::forTablePrepared('text_tags', $bindings);

        $row = Connection::preparedFetchOne($sql, $bindings);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * {@inheritdoc}
     */
    public function sourceUriExists(string $sourceUri): bool
    {
        $trimmedUri = trim($sourceUri);

        // Check texts table (includes both active and archived texts)
        return QueryBuilder::table('texts')
            ->where('source_uri', '=', $trimmedUri)
            ->existsPrepared();
    }
}
