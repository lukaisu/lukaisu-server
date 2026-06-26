<?php

/**
 * Reparse Language Texts Use Case
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

use Lukaisu\Shared\Infrastructure\Database\Maintenance;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\TextParsing;

/**
 * Use case for reparsing all texts for a language.
 */
class ReparseLanguageTexts
{
    /**
     * Refresh (reparse) all texts for a language.
     *
     * @param int $id Language ID
     *
     * @return array{sentencesDeleted: int, textItemsDeleted: int, sentencesAdded: int, textItemsAdded: int}
     */
    public function execute(int $id): array
    {
        $sentencesDeleted = QueryBuilder::table('sentences')
            ->where('language_id', '=', $id)
            ->delete();
        $textItemsDeleted = QueryBuilder::table('word_occurrences')
            ->where('language_id', '=', $id)
            ->delete();
        Maintenance::adjustAutoIncrement('sentences', 'id');

        $rows = QueryBuilder::table('texts')
            ->select(['id', 'text'])
            ->where('language_id', '=', $id)
            ->orderBy('id')
            ->getPrepared();
        foreach ($rows as $record) {
            $txtid = (int)$record["id"];
            $txttxt = (string)$record["text"];
            TextParsing::parseAndSave($txttxt, $id, $txtid);
        }

        $sentencesAdded = QueryBuilder::table('sentences')
            ->where('language_id', '=', $id)
            ->count();
        $textItemsAdded = QueryBuilder::table('word_occurrences')
            ->where('language_id', '=', $id)
            ->count();

        return [
            'sentencesDeleted' => $sentencesDeleted,
            'textItemsDeleted' => $textItemsDeleted,
            'sentencesAdded' => $sentencesAdded,
            'textItemsAdded' => $textItemsAdded
        ];
    }

    /**
     * Refresh (reparse) all texts for a language and return stats.
     *
     * @param int $id Language ID
     *
     * @return array{sentencesDeleted: int, textItemsDeleted: int, sentencesAdded: int, textItemsAdded: int}
     */
    public function refreshTexts(int $id): array
    {
        $sentencesDeleted = QueryBuilder::table('sentences')
            ->where('language_id', '=', $id)
            ->delete();
        $textItemsDeleted = QueryBuilder::table('word_occurrences')
            ->where('language_id', '=', $id)
            ->delete();
        Maintenance::adjustAutoIncrement('sentences', 'id');

        $rows = QueryBuilder::table('texts')
            ->select(['id', 'text'])
            ->where('language_id', '=', $id)
            ->orderBy('id')
            ->getPrepared();
        foreach ($rows as $record) {
            $txtid = (int)$record["id"];
            $txttxt = (string)$record["text"];
            TextParsing::parseAndSave($txttxt, $id, $txtid);
        }

        $sentencesAdded = QueryBuilder::table('sentences')
            ->where('language_id', '=', $id)
            ->count();
        $textItemsAdded = QueryBuilder::table('word_occurrences')
            ->where('language_id', '=', $id)
            ->count();

        return [
            'sentencesDeleted' => $sentencesDeleted,
            'textItemsDeleted' => $textItemsDeleted,
            'sentencesAdded' => $sentencesAdded,
            'textItemsAdded' => $textItemsAdded
        ];
    }

    /**
     * Reparse all texts for a language (internal use).
     *
     * @param int $id Language ID
     *
     * @return int Number of reparsed texts
     */
    public function reparseTexts(int $id): int
    {
        QueryBuilder::table('sentences')
            ->where('language_id', '=', $id)
            ->delete();
        QueryBuilder::table('word_occurrences')
            ->where('language_id', '=', $id)
            ->delete();
        Maintenance::adjustAutoIncrement('sentences', 'id');
        QueryBuilder::table('words')
            ->where('language_id', '=', $id)
            ->updatePrepared(['word_count' => 0]);
        Maintenance::initWordCount();

        $rows = QueryBuilder::table('texts')
            ->select(['id', 'text'])
            ->where('language_id', '=', $id)
            ->orderBy('id')
            ->getPrepared();
        $count = 0;
        foreach ($rows as $record) {
            $txtid = (int)$record["id"];
            $txttxt = (string)$record["text"];
            TextParsing::parseAndSave($txttxt, $id, $txtid);
            $count++;
        }

        return $count;
    }
}
