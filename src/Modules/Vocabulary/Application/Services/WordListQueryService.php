<?php

/**
 * Word List Query Service - Executes word list queries with filtering and pagination
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\Services;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Executes word list queries with filtering and pagination.
 *
 * Handles counting, listing, and filtering word records from the database.
 *
 * @category   Lukaisu
 * @package    Lukaisu\Modules\Vocabulary\Application\Services
 * @author     HugoFara <hugo.farajallah@protonmail.com>
 * @license    Unlicense <http://unlicense.org/>
 * @link       https://hugofara.github.io/lukaisu-server/developer/api
 */
class WordListQueryService
{
    /**
     * Count words matching the filter criteria.
     *
     * @param string $textId  Text ID filter (comma-separated IDs or empty)
     * @param string $whLang  Language condition (with ? placeholders)
     * @param string $whStat  Status condition
     * @param string $whQuery Query condition (with ? placeholders)
     * @param string $whTag   Tag condition (with ? placeholders)
     * @param array  $params  Merged binding parameters for filters
     *
     * @return int Number of matching words
     */
    public function countWords(
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $params = []
    ): int {
        if ($textId == '') {
            $bindings = $params;
            $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $sql = 'select count(*) as value from (select id from (' .
                'words left JOIN word_tag_map' .
                ' ON words.id = word_id) where (1=1) ' .
                $whLang . $whStat . $whQuery . $wordScope .
                ' group by words.id ' . $whTag . ') as dummy';
        } else {
            $bindings = [];
            $textIds = array_map('intval', explode(',', $textId));
            $inClause = Connection::buildPreparedInClause($textIds, $bindings);
            /** @var array<int, mixed> $bindings */
            $bindings = array_values(array_merge($bindings, $params));
            $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $sql = 'select count(*) as value from (select id from (' .
                'words left JOIN word_tag_map' .
                ' ON words.id = word_id), word_occurrences' .
                ' where language_id = language_id and word_id = words.id and text_id in ' .
                $inClause . $whLang . $whStat . $whQuery . $wordScope .
                ' group by words.id ' . $whTag . ') as dummy';
        }
        return (int) Connection::preparedFetchValue($sql, $bindings);
    }

    /**
     * Get words list for display.
     *
     * @param array{whLang?: string, whStat?: string, whQuery?: string,
     *               whTag?: string, textId?: string, params?: array} $filters Filter parameters
     * @param int   $sort    Sort column index
     * @param int   $page    Page number
     * @param int   $perPage Items per page
     *
     * @return array Array of word records
     */
    public function getWordsList(array $filters, int $sort, int $page, int $perPage): array
    {
        $sorts = [
            'words.text_lc',
            'lower(words.translation)',
            'words.id desc',
            'words.id asc',
            'words.status, words.text_lc',
            'words.stability',
            'textswordcount desc, words.text_lc asc'
        ];

        $lsorts = count($sorts);
        if ($sort < 1) {
            $sort = 1;
        }
        if ($sort > $lsorts) {
            $sort = $lsorts;
        }

        $whLang = $filters['whLang'] ?? '';
        $whStat = $filters['whStat'] ?? '';
        $whQuery = $filters['whQuery'] ?? '';
        $whTag = $filters['whTag'] ?? '';
        $textId = $filters['textId'] ?? '';
        $filterParams = $filters['params'] ?? [];

        if ($sort == 7) {
            // Sort by word count in texts
            return $this->getWordsListWithWordCount($filters, $sorts[$sort - 1]);
        }

        $offset = ($page - 1) * $perPage;

        if ($textId == '') {
            if ($whTag == '') {
                $bindings = $filterParams;
                $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
                $langScope = UserScopedQuery::forTablePrepared('languages', $bindings, 'languages');
                $bindings[] = $offset;
                $bindings[] = $perPage;
                $sql = 'select words.id, words.text, translation, romanization, sentence,
                        SentOK, status, name, right_to_left, google_translate_uri, Days,
                        stability AS Score, 0 AS Score2,
                        ifnull(group_concat(distinct tags.text order by tags.text separator \',\'),\'\') as taglist
                        from (select words.id, text_lc, text, translation, romanization,
                        sentence,
                        ifnull(sentence,\'\') like concat(\'%{\',words.text,\'}%\') as SentOK,
                        status, name, right_to_left, google_translate_uri,
                        DATEDIFF( NOW( ) , status_changed_at ) AS Days, stability
                        from words, languages
                        where words.language_id = languages.id ' . $whLang . $whStat . $whQuery . $wordScope . $langScope . '
                        group by words.id
                        order by ' . $sorts[$sort - 1] . ' LIMIT ?, ?) AS words
                        left JOIN word_tag_map ON words.id = word_id
                        left join tags on tags.id = tag_id
                        group by words.id
                        order by ' . $sorts[$sort - 1];
            } else {
                $bindings = $filterParams;
                $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
                $langScope = UserScopedQuery::forTablePrepared('languages', $bindings, 'languages');
                $bindings[] = $offset;
                $bindings[] = $perPage;
                $sql = 'select words.id, words.text, translation, romanization, sentence,
                        ifnull(sentence,\'\') like concat(\'%{\',words.text,\'}%\') as SentOK,
                        status, name, right_to_left, google_translate_uri,
                        DATEDIFF( NOW( ) , status_changed_at ) AS Days, stability AS Score, 0 AS Score2,
                        ifnull(group_concat(distinct tags.text order by tags.text separator \',\'),\'\') as taglist
                        from ((words left JOIN word_tag_map
                        ON words.id = word_id) left join tags
                        on tags.id = tag_id), languages
                        where words.language_id = languages.id ' . $whLang . $whStat . $whQuery . $wordScope . $langScope .
                        ' group by words.id ' . $whTag . ' order by ' . $sorts[$sort - 1] . ' LIMIT ?, ?';
            }
        } else {
            $bindings = [];
            $textIds = array_map('intval', explode(',', $textId));
            $inClause = Connection::buildPreparedInClause($textIds, $bindings);
            /** @var array<int, mixed> $bindings */
            $bindings = array_values(array_merge($bindings, $filterParams));
            $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $langScope = UserScopedQuery::forTablePrepared('languages', $bindings, 'languages');
            $bindings[] = $offset;
            $bindings[] = $perPage;
            $sql = 'select distinct words.id, words.text, translation, romanization,
                    sentence, ifnull(sentence,\'\') like \'%{%}%\' as SentOK, status,
                    name, right_to_left, google_translate_uri,
                    DATEDIFF( NOW( ) , status_changed_at ) AS Days, stability AS Score, 0 AS Score2,
                    ifnull(group_concat(distinct tags.text order by tags.text separator \',\'),\'\') as taglist
                    from ((words
                    left JOIN word_tag_map ON words.id = word_id)
                    left join tags on tags.id = tag_id),
                    languages, word_occurrences
                    where words.language_id = word_occurrences.language_id and word_id = words.id and text_id in ' .
                    $inClause . ' and words.language_id = languages.id ' . $whLang . $whStat . $whQuery . $wordScope . $langScope . '
                    group by words.id ' . $whTag . '
                    order by ' . $sorts[$sort - 1] . ' LIMIT ?, ?';
        }

        return Connection::preparedFetchAll($sql, $bindings);
    }

    /**
     * Get words list with word count (for sort option 7).
     *
     * @param array{whLang?: string, whStat?: string, whQuery?: string,
     *               whTag?: string, textId?: string, params?: array<int, int|string>} $filters Filter parameters
     * @param string $sortExpr Sort expression
     *
     * @return array Array of word records
     */
    public function getWordsListWithWordCount(array $filters, string $sortExpr): array
    {
        $whLang = $filters['whLang'] ?? '';
        $whStat = $filters['whStat'] ?? '';
        $whQuery = $filters['whQuery'] ?? '';
        $whTag = $filters['whTag'] ?? '';
        $textId = $filters['textId'] ?? '';
        $filterParams = $filters['params'] ?? [];

        if ($textId != '') {
            /** @var array<int, mixed> $bindings */
            $bindings = [];
            $textIds = array_map('intval', explode(',', $textId));
            $inClause = Connection::buildPreparedInClause($textIds, $bindings);
            foreach ($filterParams as $param) {
                $bindings[] = $param;
            }
            $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $langScope = UserScopedQuery::forTablePrepared('languages', $bindings, 'languages');
            $sql = 'select words.id, count(words.id) AS textswordcount, words.text, translation,
                    romanization, sentence,
                    ifnull(sentence,\'\') like concat(\'%{\',words.text,\'}%\') as SentOK,
                    status, name, right_to_left, google_translate_uri,
                    DATEDIFF( NOW( ) , status_changed_at ) AS Days, stability AS Score, 0 AS Score2,
                    ifnull(group_concat(distinct tags.text order by tags.text separator \',\'),\'\') as taglist,
                    text_lc, stability
                    from ((words left JOIN word_tag_map
                    ON words.id = word_id)
                    left join tags on tags.id = tag_id),
                    languages, word_occurrences
                    where words.language_id = word_occurrences.language_id and word_id = words.id and words.language_id = languages.id
                    and text_id in ' . $inClause . ' ' .
                    $whLang . $whStat . $whQuery . $wordScope . $langScope .
                    ' group by words.id ' . $whTag .
                    ' order by ' . $sortExpr;
        } else {
            // UNION query: first part = words NOT in any text, second = words with occurrences.
            // Both halves share the same filter params and the same user scope, so we duplicate
            // each set of bindings via two forTablePrepared calls.
            $bindings = $filterParams;
            $wordScope1 = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $langScope1 = UserScopedQuery::forTablePrepared('languages', $bindings, 'languages');
            // Re-append the filter params for the UNION's second half. Use
            // a foreach instead of array_merge so Psalm preserves the
            // `array<int, mixed>` shape required by forTablePrepared's
            // by-reference signature.
            foreach ($filterParams as $param) {
                $bindings[] = $param;
            }
            $wordScope2 = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $langScope2 = UserScopedQuery::forTablePrepared('languages', $bindings, 'languages');
            $sql = 'select words.id, 0 AS textswordcount, words.text, translation,
                    romanization, sentence,
                    ifnull(sentence,\'\') like concat(\'%{\',words.text,\'}%\') as SentOK,
                    status, name, right_to_left, google_translate_uri,
                    DATEDIFF( NOW( ) , status_changed_at ) AS Days, stability AS Score, 0 AS Score2,
                    ifnull(group_concat(distinct tags.text order by tags.text separator \',\'),\'\') as taglist,
                    text_lc, stability
                    from ((words left JOIN word_tag_map
                    ON words.id = word_id)
                    left join tags on tags.id = tag_id),
                    languages
                    where words.language_id = languages.id and words.id NOT IN (SELECT DISTINCT word_id
                    from word_occurrences where word_occurrences.language_id = languages.id) ' .
                    $whLang . $whStat . $whQuery . $wordScope1 . $langScope1 . '
                    group by words.id ' . $whTag . '
                    UNION
                    select words.id, count(words.id) AS textswordcount, words.text, translation,
                    romanization, sentence,
                    ifnull(sentence,\'\') like concat(\'%{\',words.text,\'}%\') as SentOK,
                    status, name, right_to_left, google_translate_uri,
                    DATEDIFF( NOW( ) , status_changed_at ) AS Days, stability AS Score, 0 AS Score2,
                    ifnull(group_concat(distinct tags.text order by tags.text separator \',\'),\'\') as taglist,
                    text_lc, stability
                    from ((words left JOIN word_tag_map
                    ON words.id = word_id)
                    left join tags on tags.id = tag_id),
                    languages, word_occurrences
                    where words.language_id = word_occurrences.language_id and word_id = words.id and words.language_id = languages.id ' .
                    $whLang . $whStat . $whQuery . $wordScope2 . $langScope2 .
                    ' group by words.id ' . $whTag .
                    ' order by ' . $sortExpr;
        }

        return Connection::preparedFetchAll($sql, $bindings);
    }

    /**
     * Get word IDs matching filter criteria (for 'all' actions).
     *
     * @param string $textId  Text ID filter (comma-separated IDs or empty)
     * @param string $whLang  Language condition (with ? placeholders)
     * @param string $whStat  Status condition
     * @param string $whQuery Query condition (with ? placeholders)
     * @param string $whTag   Tag condition (with ? placeholders)
     * @param array  $params  Merged binding parameters for filters
     *
     * @return int[] Array of word IDs
     */
    public function getFilteredWordIds(
        string $textId,
        string $whLang,
        string $whStat,
        string $whQuery,
        string $whTag,
        array $params = []
    ): array {
        if ($textId == '') {
            $bindings = $params;
            $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $sql = 'select distinct id from (
                words
                left JOIN word_tag_map
                ON words.id = word_id
            ) where (1=1) ' . $whLang . $whStat . $whQuery . $wordScope . '
            group by words.id ' . $whTag;
        } else {
            $bindings = [];
            $textIds = array_map('intval', explode(',', $textId));
            $inClause = Connection::buildPreparedInClause($textIds, $bindings);
            /** @var array<int, mixed> $bindings */
            $bindings = array_values(array_merge($bindings, $params));
            $wordScope = UserScopedQuery::forTablePrepared('words', $bindings, 'words');
            $sql = 'select distinct id
            from (
                words
                left JOIN word_tag_map ON words.id = word_id
            ), word_occurrences
            where language_id = language_id and word_id = words.id and
            text_id in ' . $inClause . $whLang . $whStat . $whQuery . $wordScope .
            ' group by words.id ' . $whTag;
        }

        $records = Connection::preparedFetchAll($sql, $bindings);
        $ids = [];
        foreach ($records as $record) {
            $ids[] = (int) $record['id'];
        }

        return $ids;
    }
}
