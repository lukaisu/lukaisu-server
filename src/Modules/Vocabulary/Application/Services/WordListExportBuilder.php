<?php

/**
 * Word List Export Builder - Builds SQL queries for word list exports
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\Services;

use Lukaisu\Shared\Infrastructure\Database\Connection;

/**
 * Builds SQL queries for word list exports (Anki, TSV, flexible, test).
 *
 * Each method returns an array with 'sql' and 'params' keys for
 * use with prepared statements.
 *
 * @category   Lukaisu
 * @package    Lukaisu\Modules\Vocabulary\Application\Services
 * @author     HugoFara <hugo.farajallah@protonmail.com>
 * @license    Unlicense <http://unlicense.org/>
 * @link       https://hugofara.github.io/lukaisu-server/developer/api
 * @since      3.0.0
 */
class WordListExportBuilder
{
    /**
     * Get Anki export SQL for selected words.
     *
     * @param int[]  $ids          Array of word IDs (empty for filter-based export)
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getAnkiExportSql(
        array $ids,
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        $ankiSelect = 'select distinct id, LgRightToLeft,
            LgRegexpWordCharacters, LgName, text, translation,
            romanization, sentence,
            ifnull(group_concat(distinct text order by text separator \' \'),\'\') as taglist';
        $ankiFrom = 'from ((words left JOIN word_tag_map ON id = word_id)
            left join tags on id = tag_id), languages';
        $ankiWhere = 'language_id = LgID AND translation != \'*\'
            AND translation != \'\' AND translation IS NOT NULL
            AND sentence IS NOT NULL AND sentence != \'\'
            AND (sentence LIKE CONCAT(\'%{\',text,\'}%\')
                 OR (sentence LIKE CONCAT(\'%\',text,\'%\') AND CHAR_LENGTH(sentence) > CHAR_LENGTH(text)))';

        if (!empty($ids)) {
            $params = [];
            $inClause = Connection::buildPreparedInClause($ids, $params);

            return [
                'sql' => "$ankiSelect $ankiFrom
                    where $ankiWhere AND translation != ''
                    and id in $inClause group by id",
                'params' => $params,
            ];
        }

        if ($textId == '') {
            return [
                'sql' => "$ankiSelect $ankiFrom
                    where $ankiWhere $whLang $whStat $whQuery
                    group by id $whTag",
                'params' => $filterParams,
            ];
        }

        $params = [];
        $textIds = array_map('intval', explode(',', $textId));
        $inClause = Connection::buildPreparedInClause($textIds, $params);
        $params = array_values(array_merge($params, $filterParams));

        return [
            'sql' => "$ankiSelect $ankiFrom, word_occurrences
                where language_id = language_id and word_id = id
                and text_id in $inClause and $ankiWhere
                $whLang $whStat $whQuery group by id $whTag",
            'params' => $params,
        ];
    }

    /**
     * Get TSV export SQL for selected words.
     *
     * @param int[]  $ids          Array of word IDs (empty for filter-based export)
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getTsvExportSql(
        array $ids,
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        $tsvSelect = 'select distinct id, LgName, text, translation,
            romanization, sentence, status,
            ifnull(group_concat(distinct text order by text separator \' \'),\'\') as taglist';
        $tsvFrom = 'from ((words left JOIN word_tag_map ON id = word_id)
            left join tags on id = tag_id), languages';

        if (!empty($ids)) {
            $params = [];
            $inClause = Connection::buildPreparedInClause($ids, $params);

            return [
                'sql' => "$tsvSelect $tsvFrom
                    where language_id = LgID and id in $inClause group by id",
                'params' => $params,
            ];
        }

        if ($textId == '') {
            return [
                'sql' => "$tsvSelect $tsvFrom
                    where language_id = LgID $whLang $whStat $whQuery
                    group by id $whTag",
                'params' => $filterParams,
            ];
        }

        $params = [];
        $textIds = array_map('intval', explode(',', $textId));
        $inClause = Connection::buildPreparedInClause($textIds, $params);
        $params = array_values(array_merge($params, $filterParams));

        return [
            'sql' => "$tsvSelect $tsvFrom, word_occurrences
                where language_id = language_id and word_id = id
                and text_id in $inClause and language_id = LgID
                $whLang $whStat $whQuery group by id $whTag",
            'params' => $params,
        ];
    }

    /**
     * Get flexible export SQL for selected words.
     *
     * @param int[]  $ids          Array of word IDs (empty for filter-based export)
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getFlexibleExportSql(
        array $ids,
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        $flexSelect = 'select distinct id, LgName, LgExportTemplate, LgRightToLeft,
            text, text_lc, translation, romanization, sentence, status,
            ifnull(group_concat(distinct text order by text separator \' \'),\'\') as taglist';
        $flexFrom = 'from ((words left JOIN word_tag_map ON id = word_id)
            left join tags on id = tag_id), languages';

        if (!empty($ids)) {
            $params = [];
            $inClause = Connection::buildPreparedInClause($ids, $params);

            return [
                'sql' => "$flexSelect $flexFrom
                    where language_id = LgID and id in $inClause group by id",
                'params' => $params,
            ];
        }

        if ($textId == '') {
            return [
                'sql' => "$flexSelect $flexFrom
                    where language_id = LgID $whLang $whStat $whQuery
                    group by id $whTag",
                'params' => $filterParams,
            ];
        }

        $params = [];
        $textIds = array_map('intval', explode(',', $textId));
        $inClause = Connection::buildPreparedInClause($textIds, $params);
        $params = array_values(array_merge($params, $filterParams));

        return [
            'sql' => "$flexSelect $flexFrom, word_occurrences
                where language_id = language_id and word_id = id
                and text_id in $inClause and language_id = LgID
                $whLang $whStat $whQuery group by id $whTag",
            'params' => $params,
        ];
    }

    /**
     * Get test SQL for selected words.
     *
     * @param string $textId       Text ID filter (comma-separated, empty for no filter)
     * @param string $whLang       Language condition (with ? placeholders)
     * @param string $whStat       Status condition
     * @param string $whQuery      Query condition (with ? placeholders)
     * @param string $whTag        Tag condition (with ? placeholders)
     * @param array  $filterParams Merged binding parameters for filter conditions
     *
     * @return array{sql: string, params: array} SQL query and parameters
     */
    public function getTestWordIdsSql(
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $filterParams = []
    ): array {
        if ($textId == '') {
            return [
                'sql' => 'select distinct id
                    from (words left JOIN word_tag_map ON id = word_id)
                    where (1=1) ' . $whLang . $whStat . $whQuery .
                    ' group by id ' . $whTag,
                'params' => $filterParams,
            ];
        }

        $params = [];
        $textIds = array_map('intval', explode(',', $textId));
        $inClause = Connection::buildPreparedInClause($textIds, $params);
        $params = array_values(array_merge($params, $filterParams));

        return [
            'sql' => 'select distinct id
                from (words left JOIN word_tag_map ON id = word_id),
                word_occurrences
                where language_id = language_id and word_id = id
                and text_id in ' . $inClause .
                $whLang . $whStat . $whQuery .
                ' group by id ' . $whTag,
            'params' => $params,
        ];
    }
}
