<?php

/**
 * Feed Article API Handler
 *
 * Handles article-related API operations: listing, deleting, importing,
 * and resetting error articles.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Feed\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Feed\Http;

use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Modules\Feed\Application\FeedFacade;

/**
 * Sub-handler for feed article API operations.
 */
class FeedArticleApiHandler
{
    private FeedFacade $feedFacade;

    public function __construct(FeedFacade $feedFacade)
    {
        $this->feedFacade = $feedFacade;
    }

    /**
     * Get articles for a feed.
     *
     * @param array $params Parameters:
     *                      - feed_id: int (required)
     *                      - query: string (search)
     *                      - page: int
     *                      - per_page: int
     *                      - sort: int (1=date desc, 2=date asc, 3=title)
     *
     * @return array{articles?: array, pagination?: array, feed?: array, error?: string}
     */
    public function getArticles(array $params): array
    {
        $feedId = (int)($params['feed_id'] ?? 0);
        if ($feedId <= 0) {
            return ['error' => 'Feed ID is required'];
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, min(100, (int)($params['per_page'] ?? 50)));
        $query = (string)($params['query'] ?? '');
        $sort = max(1, min(3, (int)($params['sort'] ?? 1)));

        // Get feed info
        $feed = $this->feedFacade->getFeedById($feedId);
        if ($feed === null) {
            return ['error' => 'Feed not found'];
        }

        // Build WHERE clause with parameters. feed_links is aliased `fl` in both the
        // count and the article query below; the texts LEFT JOIN there makes bare
        // title/description ambiguous, so qualify them here.
        $whereConditions = ["fl.feed_id = ?"];
        $queryParams = [$feedId];

        if (is_string($query) && $query !== '') {
            $pattern = '%' . str_replace('*', '%', $query) . '%';
            $whereConditions[] = "(fl.title LIKE ? OR fl.description LIKE ?)";
            $queryParams[] = $pattern;
            $queryParams[] = $pattern;
        }

        $where = implode(' AND ', $whereConditions);

        // Count total using raw SQL with fixed table name
        $total = (int)Connection::preparedFetchValue(
            "SELECT COUNT(*) AS cnt FROM feed_links fl WHERE $where",
            $queryParams,
            'cnt'
        );

        // Calculate pagination
        $totalPages = (int)ceil($total / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // Sort order (qualified: the article query LEFT JOINs texts, so bare
        // title would be ambiguous)
        $sorts = ['fl.published_at DESC', 'fl.published_at ASC', 'fl.title ASC'];
        $orderBy = $sorts[$sort - 1] ?? 'fl.published_at DESC';

        // Get articles with import status (archived texts are in texts table with archived_at)
        $sql = "SELECT fl.*, tx.id AS text_id, tx.archived_at
                FROM feed_links fl
                LEFT JOIN texts tx ON tx.source_uri = TRIM(fl.link)
                WHERE $where
                ORDER BY $orderBy
                LIMIT ?, ?";

        // Add pagination parameters
        $queryParams[] = $offset;
        $queryParams[] = $perPage;

        $articles = [];
        $rows = Connection::preparedFetchAll($sql, $queryParams);
        foreach ($rows as $row) {
            $articles[] = $this->formatArticleRecord($row);
        }

        return [
            'articles' => $articles,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages
            ],
            'feed' => [
                'id' => (int)$feed['id'],
                'name' => $feed['name'],
                'langId' => $feed['language_id']
            ]
        ];
    }

    /**
     * Format an article record for API response.
     *
     * @param array $row Database record
     *
     * @return array Formatted article data
     */
    public function formatArticleRecord(array $row): array
    {
        $textId = isset($row['text_id']) && $row['text_id'] !== null && $row['text_id'] !== ''
            ? (int)$row['text_id'] : null;
        $isArchived = $textId !== null && !empty($row['archived_at']);

        $status = 'new';
        if ($textId !== null && !$isArchived) {
            $status = 'imported';
        } elseif ($isArchived) {
            $status = 'archived';
        } elseif (str_starts_with((string)$row['link'], ' ')) {
            $status = 'error';
        }

        // For archived texts, report the same id as archivedTextId
        $archivedTextId = $isArchived ? $textId : null;
        $activeTextId = ($textId !== null && !$isArchived) ? $textId : null;

        return [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'link' => trim((string)$row['link']),
            'description' => (string)$row['description'],
            'date' => (string)$row['published_at'],
            'audio' => (string)$row['audio'],
            'hasText' => !empty($row['text']),
            'status' => $status,
            'textId' => $activeTextId,
            'archivedTextId' => $archivedTextId
        ];
    }

    /**
     * Delete articles.
     *
     * @param int   $feedId     Feed ID
     * @param array $articleIds Article IDs to delete (empty = all)
     *
     * @return array{success: bool, deleted: int, error?: string}
     */
    public function deleteArticles(int $feedId, array $articleIds = []): array
    {
        // Verify the caller owns the target feed before doing any deletes.
        // getFeedById flows through MySqlFeedRepository::find(), which is
        // user-scoped in multi-user mode (news_feeds is in USER_SCOPED_TABLES
        // and find() also asserts user_id at the repository boundary), so a
        // foreign feedId returns null and we bail out before touching
        // feed_links — which has no user_id column of its own and would
        // otherwise let any logged-in user wipe any other user's articles.
        $feed = $this->feedFacade->getFeedById($feedId);
        if ($feed === null) {
            return ['success' => false, 'deleted' => 0, 'error' => 'Feed not found'];
        }

        if (empty($articleIds)) {
            // Delete all articles for feed (article repo also re-checks
            // feed ownership, so this stays safe even if the upstream
            // gate above is removed).
            $deleted = $this->feedFacade->deleteArticles((string)$feedId);
        } else {
            // Delete specific articles. The whereIn on feed_id alone
            // doesn't gate ownership because feed_links has no user_id
            // column; the getFeedById check above is what makes it safe.
            $ids = array_map('intval', $articleIds);
            $deleted = QueryBuilder::table('feed_links')
                ->whereIn('id', $ids)
                ->whereIn('feed_id', [$feedId])
                ->delete();
        }

        return [
            'success' => true,
            'deleted' => $deleted
        ];
    }

    /**
     * Import articles as texts.
     *
     * @param array $data Import data:
     *                    - article_ids: array of article IDs
     *
     * @return array{success: bool, imported: int, errors: array}
     */
    public function importArticles(array $data): array
    {
        $articleIds = $data['article_ids'] ?? [];
        if (!is_array($articleIds) || count($articleIds) === 0) {
            return ['success' => false, 'imported' => 0, 'errors' => ['No articles selected']];
        }

        $ids = implode(',', array_map('intval', $articleIds));
        $feedLinks = $this->feedFacade->getMarkedFeedLinks($ids);

        $imported = 0;
        $errors = [];

        foreach ($feedLinks as $row) {
            /** @var array<string, mixed> $row */
            $nfOptions = (string)($row['options'] ?? '');
            $nfName = (string)($row['name'] ?? '');

            $tagNameRaw = $this->feedFacade->getNfOption($nfOptions, 'tag');
            $tagName = is_string($tagNameRaw) && $tagNameRaw !== '' ? $tagNameRaw : mb_substr($nfName, 0, 20, 'utf-8');

            $maxTextsRaw = $this->feedFacade->getNfOption($nfOptions, 'max_texts');
            $maxTexts = is_string($maxTextsRaw) ? (int)$maxTextsRaw : 0;
            if (!$maxTexts) {
                $maxTexts = (int)Settings::getWithDefault('set-max-texts-per-feed');
            }

            $flLink = (string)($row['link'] ?? '');
            $flId = (string)($row['id'] ?? '');
            $doc = [[
                'link' => empty($flLink) ? ('#' . $flId) : $flLink,
                'title' => (string)($row['title'] ?? ''),
                'audio' => (string)($row['audio'] ?? ''),
                'text' => (string)($row['text'] ?? '')
            ]];

            $charsetRaw = $this->feedFacade->getNfOption($nfOptions, 'charset');
            $charset = is_string($charsetRaw) ? $charsetRaw : null;
            $texts = $this->feedFacade->extractTextFromArticle(
                $doc,
                (string)($row['article_section_tags'] ?? ''),
                (string)($row['filter_tags'] ?? ''),
                $charset
            );

            if (isset($texts['error'])) {
                /** @var array{message?: string, link?: string[]} $errorData */
                $errorData = $texts['error'];
                $errors[] = $errorData['message'] ?? 'Unknown error';
                foreach ($errorData['link'] ?? [] as $errLink) {
                    $this->feedFacade->markLinkAsError($errLink);
                }
                unset($texts['error']);
            }

            if (is_array($texts)) {
                foreach ($texts as $text) {
                    /** @var array{title?: mixed, text?: mixed, audio_uri?: mixed, source_uri?: mixed} $text */
                    $this->feedFacade->createTextFromFeed([
                        'language_id' => (int)($row['language_id'] ?? 0),
                        'title' => (string)($text['title'] ?? ''),
                        'text' => (string)($text['text'] ?? ''),
                        'audio_uri' => (string)($text['audio_uri'] ?? ''),
                        'source_uri' => (string)($text['source_uri'] ?? '')
                    ], $tagName);
                    $imported++;
                }
            }

            $this->feedFacade->archiveOldTexts($tagName, $maxTexts);
        }

        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        ];
    }

    /**
     * Reset error articles (remove leading space from links).
     *
     * @param int $feedId Feed ID
     *
     * @return array{success: bool, reset: int}
     */
    public function resetErrorArticles(int $feedId): array
    {
        $reset = $this->feedFacade->resetUnloadableArticles((string)$feedId);
        return [
            'success' => true,
            'reset' => $reset
        ];
    }

    // =========================================================================
    // Format Wrappers
    // =========================================================================

    /**
     * Format response for getting articles.
     *
     * @param array $params Filter parameters
     *
     * @return array Articles with pagination
     */
    public function formatGetArticles(array $params): array
    {
        return $this->getArticles($params);
    }

    /**
     * Format response for deleting articles.
     *
     * @param int   $feedId     Feed ID
     * @param array $articleIds Article IDs (empty = all)
     *
     * @return array Deletion result
     */
    public function formatDeleteArticles(int $feedId, array $articleIds = []): array
    {
        return $this->deleteArticles($feedId, $articleIds);
    }

    /**
     * Format response for importing articles.
     *
     * @param array $data Import data
     *
     * @return array Import result
     */
    public function formatImportArticles(array $data): array
    {
        return $this->importArticles($data);
    }

    /**
     * Format response for resetting error articles.
     *
     * @param int $feedId Feed ID
     *
     * @return array Reset result
     */
    public function formatResetErrorArticles(int $feedId): array
    {
        return $this->resetErrorArticles($feedId);
    }
}
