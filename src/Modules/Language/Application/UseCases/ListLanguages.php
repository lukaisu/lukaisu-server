<?php

/**
 * List Languages Use Case
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

use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Language\Domain\LanguageRepositoryInterface;
use Lukaisu\Modules\Language\Infrastructure\MySqlLanguageRepository;

/**
 * Use case for listing languages with various formats and statistics.
 *
 * @since 3.0.0
 */
class ListLanguages
{
    private LanguageRepositoryInterface $repository;

    /**
     * @param LanguageRepositoryInterface|null $repository Repository instance
     */
    public function __construct(?LanguageRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new MySqlLanguageRepository();
    }

    /**
     * Get all languages as a name => id dictionary.
     *
     * @return array<string, int>
     */
    public function getAllLanguages(): array
    {
        return $this->repository->getAllAsDict();
    }

    /**
     * Get languages formatted for select dropdown options.
     *
     * @param int $maxNameLength Maximum name length before truncation
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getLanguagesForSelect(int $maxNameLength = 30): array
    {
        return $this->repository->getForSelect($maxNameLength);
    }

    /**
     * Get languages with statistics for display.
     *
     * @return array Language data with counts
     */
    public function getLanguagesWithStats(): array
    {
        // Get base language data
        $records = QueryBuilder::table('languages')
            ->select(['id', 'name', 'export_template'])
            ->where('name', '<>', '')
            ->orderBy('name')
            ->getPrepared();

        // Get feed counts
        $feedCounts = $this->getFeedCounts();
        $articleCounts = $this->getArticleCounts();

        $languages = [];
        foreach ($records as $record) {
            $lid = (int)$record['id'];
            $stats = $this->getRelatedDataCounts($lid);

            $languages[] = [
                'id' => $lid,
                'name' => $record['name'],
                'hasExportTemplate' => !empty($record['export_template']),
                'textCount' => $stats['texts'],
                'archivedTextCount' => $stats['archivedTexts'],
                'wordCount' => $stats['words'],
                'feedCount' => $feedCounts[$lid] ?? 0,
                'articleCount' => $articleCounts[$lid] ?? 0,
            ];
        }

        return $languages;
    }

    /**
     * Get languages that have at least one text, with text counts.
     *
     * @return array<int, array{id: int, name: string, text_count: int}>
     */
    public function getLanguagesWithTextCounts(): array
    {
        $records = QueryBuilder::table('languages')
            ->select(['languages.id', 'languages.name', 'COUNT(texts.id) AS text_count'])
            ->join('texts', 'texts.language_id', '=', 'languages.id')
            ->where('languages.name', '<>', '')
            ->groupBy(['languages.id', 'languages.name'])
            ->orderBy('languages.name')
            ->getPrepared();

        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'id' => (int)$record['id'],
                'name' => (string)$record['name'],
                'text_count' => (int)$record['text_count']
            ];
        }
        return $result;
    }

    /**
     * Get languages that have at least one archived text, with archived text counts.
     *
     * @return array<int, array{id: int, name: string, text_count: int}>
     */
    public function getLanguagesWithArchivedTextCounts(): array
    {
        $records = QueryBuilder::table('languages')
            ->select(['languages.id', 'languages.name', 'COUNT(texts.id) AS text_count'])
            ->join('texts', 'texts.language_id', '=', 'languages.id')
            ->where('languages.name', '<>', '')
            ->whereNotNull('texts.archived_at')
            ->groupBy(['languages.id', 'languages.name'])
            ->orderBy('languages.name')
            ->getPrepared();

        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'id' => (int)$record['id'],
                'name' => (string)$record['name'],
                'text_count' => (int)$record['text_count']
            ];
        }
        return $result;
    }

    /**
     * Get counts of related data for a language.
     *
     * @param int $lid Language ID
     *
     * @return array{texts: int, archivedTexts: int, words: int, feeds: int}
     */
    public function getRelatedDataCounts(int $lid): array
    {
        return [
            'texts' => QueryBuilder::table('texts')
                ->where('language_id', '=', $lid)
                ->whereNull('archived_at')
                ->count(),
            'archivedTexts' => QueryBuilder::table('texts')
                ->where('language_id', '=', $lid)
                ->whereNotNull('archived_at')
                ->count(),
            'words' => QueryBuilder::table('words')
                ->where('language_id', '=', $lid)
                ->count(),
            'feeds' => QueryBuilder::table('news_feeds')
                ->where('language_id', '=', $lid)
                ->count(),
        ];
    }

    /**
     * Get feed counts per language.
     *
     * @return array<int, int> Language ID => feed count
     */
    private function getFeedCounts(): array
    {
        $records = QueryBuilder::table('news_feeds')
            ->select(['language_id', 'COUNT(*) as value'])
            ->groupBy('language_id')
            ->getPrepared();
        $counts = [];
        foreach ($records as $record) {
            $counts[(int)$record['language_id']] = (int)$record['value'];
        }
        return $counts;
    }

    /**
     * Get article counts per language.
     *
     * @return array<int, int> Language ID => article count
     */
    private function getArticleCounts(): array
    {
        $records = QueryBuilder::table('news_feeds')
            ->selectRaw('news_feeds.language_id, COUNT(*) AS article_count')
            ->join('feed_links', 'news_feeds.id', '=', 'feed_links.feed_id')
            ->groupBy('news_feeds.language_id')
            ->getPrepared();
        $counts = [];
        foreach ($records as $record) {
            $counts[(int)$record['language_id']] = (int)$record['article_count'];
        }
        return $counts;
    }
}
