<?php

/**
 * Import Articles Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Feed\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Feed\Application\UseCases;

use Lukaisu\Modules\Feed\Application\Services\ArticleExtractor;
use Lukaisu\Modules\Feed\Domain\ArticleRepositoryInterface;
use Lukaisu\Modules\Feed\Domain\FeedRepositoryInterface;
use Lukaisu\Modules\Feed\Domain\TextCreationInterface;

/**
 * Use case for importing articles as texts.
 *
 * Converts feed articles to Lukaisu Server texts by:
 * 1. Extracting content from article URLs
 * 2. Creating text entries with parsing
 * 3. Applying tags and archiving old texts
 */
class ImportArticles
{
    /**
     * Constructor.
     *
     * @param ArticleRepositoryInterface $articleRepository Article repository
     * @param FeedRepositoryInterface    $feedRepository    Feed repository
     * @param TextCreationInterface      $textCreation      Text creation adapter
     * @param ArticleExtractor           $articleExtractor  Article extractor service
     */
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private FeedRepositoryInterface $feedRepository,
        private TextCreationInterface $textCreation,
        private ArticleExtractor $articleExtractor
    ) {
    }

    /**
     * Execute the use case.
     *
     * @param int[] $articleIds Article IDs to import
     *
     * @return array{
     *     imported: int,
     *     failed: int,
     *     archived: int,
     *     errors: array<string, string[]>
     * }
     */
    public function execute(array $articleIds): array
    {
        $result = [
            'imported' => 0,
            'failed' => 0,
            'archived' => 0,
            'errors' => [],
        ];

        if (empty($articleIds)) {
            return $result;
        }

        // Get articles with their feed data
        $articles = $this->articleRepository->findByIds($articleIds);

        // Group articles by feed for batch processing
        $byFeed = [];
        foreach ($articles as $article) {
            $feedId = $article->feedId();
            if (!isset($byFeed[$feedId])) {
                $byFeed[$feedId] = [];
            }
            $byFeed[$feedId][] = $article;
        }

        // Process each feed's articles
        foreach ($byFeed as $feedId => $feedArticles) {
            $feed = $this->feedRepository->find($feedId);
            if ($feed === null) {
                continue;
            }

            $feedResult = $this->importFeedArticles($feed, $feedArticles);
            $result['imported'] = (int)$result['imported'] + $feedResult['imported'];
            $result['failed'] = (int)$result['failed'] + $feedResult['failed'];
            $result['archived'] = (int)$result['archived'] + $feedResult['archived'];

            if ($feedResult['errors'] !== null && count($feedResult['errors']) > 0) {
                $result['errors'][(string) $feedId] = $feedResult['errors'];
            }
        }

        /** @var array{imported: int, failed: int, archived: int, errors: array<string, string[]>} $result */
        return $result;
    }

    /**
     * Import articles for a specific feed.
     *
     * @param \Lukaisu\Modules\Feed\Domain\Feed          $feed     Feed entity
     * @param array<\Lukaisu\Modules\Feed\Domain\Article> $articles Article entities
     *
     * @return array{imported: int, failed: int, archived: int, errors: string[]} Import result
     */
    private function importFeedArticles($feed, array $articles): array
    {
        $result = [
            'imported' => 0,
            'failed' => 0,
            'archived' => 0,
            'errors' => [],
        ];

        $options = $feed->options();
        $tagName = $options->get('tag') ?? ('feed_' . (int)$feed->id());
        $maxTexts = (int) ($options->get('max_texts') ?? 100);
        $charset = $options->get('charset');

        // Prepare article data for extraction
        /** @var array<int, array{link: string, title: string, audio?: string, text?: string}> $feedData */
        $feedData = [];
        foreach ($articles as $article) {
            $feedData[] = [
                'title' => $article->title(),
                'link' => $article->link(),
                'audio' => $article->audio(),
                'text' => $article->text(),
            ];
        }

        // Extract content from articles
        $extracted = $this->articleExtractor->extract(
            $feedData,
            $feed->articleSectionTags(),
            $feed->filterTags(),
            $charset
        );

        // Process extracted content
        foreach ($extracted as $key => $data) {
            if ($key === 'error') {
                /** @var array{link?: string[]} $data */
                $errorLinks = $data['link'] ?? [];
                $result['errors'] = $errorLinks;
                $result['failed'] += count($errorLinks);

                // Mark failed articles as error
                foreach ($errorLinks as $link) {
                    $this->articleRepository->markAsError($link);
                }
                continue;
            }

            /** @var array{source_uri: string, title: string, text: string, audio_uri?: string} $data */
            // Skip if already imported
            if ($this->textCreation->sourceUriExists($data['source_uri'])) {
                continue;
            }

            // Create text
            try {
                $this->textCreation->createText(
                    $feed->languageId(),
                    $data['title'],
                    $data['text'],
                    $data['audio_uri'] ?? '',
                    $data['source_uri'],
                    $tagName
                );
                $result['imported']++;
            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = $data['source_uri'];
            }
        }

        // Archive old texts if needed
        if ($maxTexts > 0 && $result['imported'] > 0) {
            $archiveResult = $this->textCreation->archiveOldTexts($tagName, $maxTexts);
            $result['archived'] = $archiveResult['archived'];
        }

        return $result;
    }
}
