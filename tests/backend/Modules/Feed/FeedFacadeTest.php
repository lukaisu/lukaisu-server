<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Feed;

use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Modules\Feed\Application\FeedFacade;
use Lukaisu\Modules\Feed\Application\Services\ArticleExtractor;
use Lukaisu\Modules\Feed\Application\Services\RssParser;
use Lukaisu\Modules\Feed\Domain\Article;
use Lukaisu\Modules\Feed\Domain\ArticleRepositoryInterface;
use Lukaisu\Modules\Feed\Domain\Feed;
use Lukaisu\Modules\Feed\Domain\FeedOptions;
use Lukaisu\Modules\Feed\Domain\FeedRepositoryInterface;
use Lukaisu\Modules\Feed\Domain\TextCreationInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the FeedFacade class.
 *
 * Tests feed and article operations including CRUD, RSS parsing, and utilities.
 */
class FeedFacadeTest extends TestCase
{
    private static bool $dbConnected = false;

    /** @var FeedRepositoryInterface&MockObject */
    private FeedRepositoryInterface $feedRepository;

    /** @var ArticleRepositoryInterface&MockObject */
    private ArticleRepositoryInterface $articleRepository;

    /** @var TextCreationInterface&MockObject */
    private TextCreationInterface $textCreation;

    /** @var RssParser&MockObject */
    private RssParser $rssParser;

    /** @var ArticleExtractor&MockObject */
    private ArticleExtractor $articleExtractor;

    private FeedFacade $facade;

    public static function setUpBeforeClass(): void
    {
        $config = EnvLoader::getDatabaseConfig();
        $testDbname = "test_" . $config['dbname'];

        if (!Globals::getDbConnection()) {
            try {
                $connection = Configuration::connect(
                    $config['server'],
                    $config['userid'],
                    $config['passwd'],
                    $testDbname,
                    $config['socket'] ?? ''
                );
                Globals::setDbConnection($connection);
                self::$dbConnected = true;
            } catch (\Exception $e) {
                self::$dbConnected = false;
            }
        } else {
            self::$dbConnected = true;
        }
    }

    protected function setUp(): void
    {
        $this->feedRepository = $this->createMock(FeedRepositoryInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->textCreation = $this->createMock(TextCreationInterface::class);
        $this->rssParser = $this->createMock(RssParser::class);
        $this->articleExtractor = $this->createMock(ArticleExtractor::class);

        $this->facade = new FeedFacade(
            $this->feedRepository,
            $this->articleRepository,
            $this->textCreation,
            $this->rssParser,
            $this->articleExtractor
        );
    }

    // ===== Constructor tests =====

    public function testConstructorCreatesValidFacade(): void
    {
        $facade = new FeedFacade(
            $this->feedRepository,
            $this->articleRepository,
            $this->textCreation,
            $this->rssParser,
            $this->articleExtractor
        );
        $this->assertInstanceOf(FeedFacade::class, $facade);
    }

    public function testConstructorAcceptsMockDependencies(): void
    {
        $this->assertInstanceOf(FeedFacade::class, $this->facade);
    }

    // ===== Feed CRUD tests =====

    public function testGetFeedsReturnsEmptyArrayWhenNoFeeds(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->facade->getFeeds();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFeedsReturnsArrayOfFeedData(): void
    {
        $feed = Feed::reconstitute(
            1,
            1,
            'Test Feed',
            'https://example.com/feed.xml',
            '//article',
            '//ads',
            1234567890,
            'tag=test'
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$feed]);

        $result = $this->facade->getFeeds();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('Test Feed', $result[0]['name']);
        $this->assertEquals('https://example.com/feed.xml', $result[0]['source_uri']);
    }

    public function testGetFeedsWithLanguageFilter(): void
    {
        $feed = Feed::reconstitute(
            1,
            2,
            'German Feed',
            'https://example.com/de.xml',
            '',
            '',
            0,
            ''
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('findByLanguage')
            ->with(2)
            ->willReturn([$feed]);

        $result = $this->facade->getFeeds(2);

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result[0]['language_id']);
    }

    public function testGetFeedByIdReturnsNullWhenNotFound(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->facade->getFeedById(999);
        $this->assertNull($result);
    }

    public function testGetFeedByIdReturnsFeedArray(): void
    {
        $feed = Feed::reconstitute(
            5,
            1,
            'My Feed',
            'https://example.com/rss',
            '//content',
            '',
            time(),
            'autoupdate=1h'
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($feed);

        $result = $this->facade->getFeedById(5);

        $this->assertIsArray($result);
        $this->assertEquals(5, $result['id']);
        $this->assertEquals('My Feed', $result['name']);
        $this->assertEquals('autoupdate=1h', $result['options']);
    }

    public function testCountFeedsReturnsInteger(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('countFeeds')
            ->with(null, null)
            ->willReturn(10);

        $result = $this->facade->countFeeds();
        $this->assertEquals(10, $result);
    }

    public function testCountFeedsWithFilters(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('countFeeds')
            ->with(1, '%news%')
            ->willReturn(3);

        $result = $this->facade->countFeeds(1, '%news%');
        $this->assertEquals(3, $result);
    }

    public function testCreateFeedReturnsNewId(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Feed $feed) {
                $feed->setId(42);
                return 42;
            });

        $data = [
            'language_id' => 1,
            'name' => 'New Feed',
            'source_uri' => 'https://example.com/new.xml',
            'article_section_tags' => '//article',
            'filter_tags' => '//ad',
            'options' => 'tag=news,',
        ];

        $result = $this->facade->createFeed($data);
        $this->assertEquals(42, $result);
    }

    public function testCreateFeedTrimsTrailingCommaFromOptions(): void
    {
        $savedFeed = null;
        $this->feedRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Feed $feed) use (&$savedFeed) {
                $savedFeed = $feed;
                $feed->setId(1);
                return 1;
            });

        $this->facade->createFeed([
            'language_id' => 1,
            'name' => 'Test',
            'source_uri' => 'https://example.com/feed.xml',
            'options' => 'tag=test,autoupdate=1h,',
        ]);

        $this->assertNotNull($savedFeed);
        $optionsString = $savedFeed->options()->toString();
        $this->assertFalse(
            str_ends_with($optionsString, ','),
            "Options string should not end with comma: $optionsString"
        );
    }

    public function testUpdateFeedCallsRepository(): void
    {
        $existingFeed = Feed::reconstitute(
            5,
            1,
            'Old Name',
            'https://old.com/feed.xml',
            '',
            '',
            0,
            ''
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($existingFeed);

        $this->feedRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Feed $feed) {
                return $feed->name() === 'New Name' &&
                       $feed->sourceUri() === 'https://new.com/feed.xml';
            }));

        $this->facade->updateFeed(5, [
            'language_id' => 1,
            'name' => 'New Name',
            'source_uri' => 'https://new.com/feed.xml',
            'article_section_tags' => '',
            'filter_tags' => '',
            'options' => '',
        ]);
    }

    public function testDeleteFeedsReturnsDeleteCounts(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('deleteMultiple')
            ->with([1, 2, 3])
            ->willReturn(3);

        $this->articleRepository
            ->expects($this->once())
            ->method('deleteByFeeds')
            ->with([1, 2, 3])
            ->willReturn(15);

        $result = $this->facade->deleteFeeds('1,2,3');

        $this->assertIsArray($result);
        $this->assertEquals(3, $result['feeds']);
        $this->assertEquals(15, $result['articles']);
    }

    // ===== Article operations tests =====

    public function testGetFeedLinksReturnsEmptyArrayWhenNoArticles(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findByFeedsWithStatus')
            ->willReturn([]);

        $result = $this->facade->getFeedLinks('1,2');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFeedLinksReturnsArticleData(): void
    {
        $article = Article::reconstitute(
            1,
            1,
            'Article Title',
            'https://example.com/article',
            'Article description',
            '2025-01-01 10:00:00',
            '',
            ''
        );

        $this->articleRepository
            ->expects($this->once())
            ->method('findByFeedsWithStatus')
            ->willReturn([
                [
                    'article' => $article,
                    'text_id' => null,
                    'archived_id' => null,
                    'status' => 'new'
                ]
            ]);

        $result = $this->facade->getFeedLinks('1');

        $this->assertCount(1, $result);
        $this->assertEquals('Article Title', $result[0]['title']);
        $this->assertEquals('https://example.com/article', $result[0]['link']);
    }

    public function testGetFeedLinksPassesSearchDirectly(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findByFeedsWithStatus')
            ->with(
                [1],
                0,
                50,
                'published_at',
                'DESC',
                'test'  // Search passed directly
            )
            ->willReturn([]);

        $this->facade->getFeedLinks('1', 'test');
    }

    public function testCountFeedLinksReturnsInteger(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('countByFeeds')
            ->with([1, 2], '')
            ->willReturn(25);

        $result = $this->facade->countFeedLinks('1,2');
        $this->assertEquals(25, $result);
    }

    public function testDeleteArticlesReturnsCount(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('deleteByFeeds')
            ->with([1, 2])
            ->willReturn(10);

        // updateTimestamp is called for each feed ID
        $this->feedRepository
            ->expects($this->exactly(2))
            ->method('updateTimestamp');

        $result = $this->facade->deleteArticles('1,2');
        $this->assertEquals(10, $result);
    }

    public function testResetUnloadableArticlesReturnsCount(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('resetErrorsByFeeds')
            ->with([1, 2, 3])
            ->willReturn(5);

        $result = $this->facade->resetUnloadableArticles('1,2,3');
        $this->assertEquals(5, $result);
    }

    public function testMarkLinkAsErrorCallsRepository(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('markAsError')
            ->with('https://example.com/broken');

        $this->facade->markLinkAsError('https://example.com/broken');
    }

    public function testGetMarkedFeedLinksWithArrayInput(): void
    {
        $article = Article::reconstitute(
            1,
            10,
            'Test Article',
            'https://example.com/test',
            'Description',
            '2025-01-01 00:00:00',
            '',
            'Article text'
        );

        $feed = Feed::reconstitute(
            10,
            1,
            'Test Feed',
            'https://example.com/feed',
            '',
            '',
            0,
            ''
        );

        $this->articleRepository
            ->expects($this->once())
            ->method('findByIds')
            ->with([1, 2])
            ->willReturn([$article]);

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($feed);

        $result = $this->facade->getMarkedFeedLinks([1, 2]);

        $this->assertCount(1, $result);
        $this->assertEquals('Test Article', $result[0]['title']);
        $this->assertEquals('Test Feed', $result[0]['name']);
    }

    public function testGetMarkedFeedLinksWithStringInput(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findByIds')
            ->with([1, 2, 3])
            ->willReturn([]);

        $result = $this->facade->getMarkedFeedLinks('1,2,3');
        $this->assertEmpty($result);
    }

    // ===== RSS Feed operations tests =====

    public function testParseRssFeedDelegatesToParser(): void
    {
        $expected = [
            ['title' => 'Article 1', 'link' => 'https://example.com/1'],
            ['title' => 'Article 2', 'link' => 'https://example.com/2'],
        ];

        $this->rssParser
            ->expects($this->once())
            ->method('parse')
            ->with('https://example.com/feed.xml', '//article')
            ->willReturn($expected);

        $result = $this->facade->parseRssFeed('https://example.com/feed.xml', '//article');
        $this->assertEquals($expected, $result);
    }

    public function testParseRssFeedReturnsFalseOnError(): void
    {
        $this->rssParser
            ->expects($this->once())
            ->method('parse')
            ->with('https://invalid.com/feed', '')
            ->willReturn(null);

        $result = $this->facade->parseRssFeed('https://invalid.com/feed', '');
        $this->assertFalse($result);
    }

    public function testDetectAndParseFeedDelegatesToParser(): void
    {
        $expected = [
            ['title' => 'Item 1', 'link' => 'https://example.com/1'],
            'feed_text' => 'description',
            'feed_title' => 'My Feed',
        ];

        $this->rssParser
            ->expects($this->once())
            ->method('detectAndParse')
            ->with('https://example.com/detect')
            ->willReturn($expected);

        $result = $this->facade->detectAndParseFeed('https://example.com/detect');
        $this->assertEquals($expected, $result);
    }

    public function testDetectAndParseFeedReturnsFalseOnError(): void
    {
        $this->rssParser
            ->expects($this->once())
            ->method('detectAndParse')
            ->willReturn(null);

        $result = $this->facade->detectAndParseFeed('https://invalid.com');
        $this->assertFalse($result);
    }

    public function testExtractTextFromArticleDelegatesToExtractor(): void
    {
        $feedData = [
            ['title' => 'Test', 'link' => 'https://example.com/test'],
        ];
        $expected = [
            0 => ['title' => 'Test', 'text' => 'Content'],
        ];

        $this->articleExtractor
            ->expects($this->once())
            ->method('extract')
            ->with($feedData, '//article', '//ad', 'UTF-8')
            ->willReturn($expected);

        $result = $this->facade->extractTextFromArticle(
            $feedData,
            '//article',
            '//ad',
            'UTF-8'
        );

        $this->assertEquals($expected, $result);
    }

    public function testLoadFeedDelegatesToUseCase(): void
    {
        $feed = Feed::reconstitute(
            1,
            1,
            'Test Feed',
            'https://example.com/feed.xml',
            '',
            '',
            0,
            ''
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($feed);

        $this->rssParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn([
                ['title' => 'New Article', 'link' => 'https://example.com/new']
            ]);

        $this->articleRepository
            ->expects($this->once())
            ->method('insertBatch')
            ->willReturn(['inserted' => 1, 'duplicates' => 0]);

        $this->feedRepository
            ->expects($this->once())
            ->method('updateTimestamp');

        $result = $this->facade->loadFeed(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testGetFeedsNeedingAutoUpdateReturnsArray(): void
    {
        $feed = Feed::reconstitute(
            1,
            1,
            'Auto Feed',
            'https://example.com/auto.xml',
            '',
            '',
            time() - 7200,
            'autoupdate=1h'
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('findNeedingAutoUpdate')
            ->willReturn([$feed]);

        $result = $this->facade->getFeedsNeedingAutoUpdate();

        $this->assertCount(1, $result);
        $this->assertEquals('Auto Feed', $result[0]['name']);
    }

    // ===== Text creation tests =====

    public function testCreateTextFromFeedDelegatesToTextCreation(): void
    {
        $this->textCreation
            ->expects($this->once())
            ->method('createText')
            ->with(1, 'Test Title', 'Test content', 'audio.mp3', 'https://source.com', 'news')
            ->willReturn(123);

        $result = $this->facade->createTextFromFeed([
            'language_id' => 1,
            'title' => 'Test Title',
            'text' => 'Test content',
            'audio_uri' => 'audio.mp3',
            'source_uri' => 'https://source.com',
        ], 'news');

        $this->assertEquals(123, $result);
    }

    public function testArchiveOldTextsDelegatesToTextCreation(): void
    {
        $expected = ['archived' => 5, 'sentences' => 100, 'textitems' => 500];

        $this->textCreation
            ->expects($this->once())
            ->method('archiveOldTexts')
            ->with('news', 10)
            ->willReturn($expected);

        $result = $this->facade->archiveOldTexts('news', 10);
        $this->assertEquals($expected, $result);
    }

    public function testImportArticlesDelegatesToUseCase(): void
    {
        $article = Article::reconstitute(
            1,
            1,
            'Import Me',
            'https://example.com/article',
            'Description',
            '2025-01-01 00:00:00',
            '',
            'Full text content'
        );

        $feed = Feed::reconstitute(
            1,
            1,
            'Test Feed',
            'https://example.com/feed.xml',
            '//article',
            '',
            0,
            'tag=imported'
        );

        $this->articleRepository
            ->expects($this->once())
            ->method('findByIds')
            ->with([1])
            ->willReturn([$article]);

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($feed);

        // ArticleExtractor returns extracted data
        $this->articleExtractor
            ->expects($this->once())
            ->method('extract')
            ->willReturn([
                0 => [
                    'title' => 'Import Me',
                    'text' => 'Extracted text content',
                    'audio_uri' => '',
                    'source_uri' => 'https://example.com/article',
                ],
            ]);

        $this->textCreation
            ->expects($this->once())
            ->method('sourceUriExists')
            ->with('https://example.com/article')
            ->willReturn(false);

        $this->textCreation
            ->expects($this->once())
            ->method('createText')
            ->with(
                1,
                'Import Me',
                'Extracted text content',
                '',
                'https://example.com/article',
                'imported'
            )
            ->willReturn(100);

        $this->textCreation
            ->expects($this->once())
            ->method('archiveOldTexts')
            ->willReturn(['archived' => 0, 'sentences' => 0, 'textitems' => 0]);

        $result = $this->facade->importArticles([1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('imported', $result);
        $this->assertEquals(1, $result['imported']);
    }

    // ===== Utility methods tests =====

    public function testGetNfOptionReturnsValueForExistingKey(): void
    {
        $result = $this->facade->getNfOption('tag=news,autoupdate=1h,max_texts=10', 'tag');
        $this->assertEquals('news', $result);
    }

    public function testGetNfOptionReturnsNullForMissingKey(): void
    {
        $result = $this->facade->getNfOption('tag=news,autoupdate=1h', 'charset');
        $this->assertNull($result);
    }

    public function testGetNfOptionReturnsAllAsArray(): void
    {
        $result = $this->facade->getNfOption('tag=news,autoupdate=1h,max_texts=10', 'all');

        $this->assertIsArray($result);
        $this->assertEquals('news', $result['tag']);
        $this->assertEquals('1h', $result['autoupdate']);
        $this->assertEquals('10', $result['max_texts']);
    }

    public function testGetNfOptionReturnsEmptyArrayForEmptyString(): void
    {
        $result = $this->facade->getNfOption('', 'all');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetNfOptionReturnsNullForEmptyStringWithSpecificKey(): void
    {
        $result = $this->facade->getNfOption('', 'tag');
        $this->assertNull($result);
    }

    public function testParseAutoUpdateIntervalWithHours(): void
    {
        $result = $this->facade->parseAutoUpdateInterval('2h');
        $this->assertEquals(7200, $result);  // 2 * 60 * 60
    }

    public function testParseAutoUpdateIntervalWithDays(): void
    {
        $result = $this->facade->parseAutoUpdateInterval('1d');
        $this->assertEquals(86400, $result);  // 24 * 60 * 60
    }

    public function testParseAutoUpdateIntervalWithWeeks(): void
    {
        $result = $this->facade->parseAutoUpdateInterval('1w');
        $this->assertEquals(604800, $result);  // 7 * 24 * 60 * 60
    }

    public function testParseAutoUpdateIntervalWithInvalidFormat(): void
    {
        // Method returns null for strings without h/d/w characters
        // Note: 'invalid' contains 'd', so we use a truly invalid string
        $result = $this->facade->parseAutoUpdateInterval('test');
        $this->assertNull($result);
    }

    public function testParseAutoUpdateIntervalWithInvalidContainingD(): void
    {
        // 'invalid' contains 'd', so it matches days pattern but extracts 0
        $result = $this->facade->parseAutoUpdateInterval('invalid');
        // (int)'invali' = 0, so result is 0 seconds
        $this->assertEquals(0, $result);
    }

    public function testParseAutoUpdateIntervalWithEmptyString(): void
    {
        $result = $this->facade->parseAutoUpdateInterval('');
        $this->assertNull($result);
    }

    public function testParseAutoUpdateIntervalWithZeroHours(): void
    {
        // 0h should return 0 (0 seconds)
        $result = $this->facade->parseAutoUpdateInterval('0h');
        $this->assertEquals(0, $result);
    }

    public function testParseAutoUpdateIntervalWithMultipleHours(): void
    {
        $result = $this->facade->parseAutoUpdateInterval('12h');
        $this->assertEquals(43200, $result);  // 12 * 60 * 60
    }

    public function testFormatLastUpdateReturnsUpToDateForZeroDiff(): void
    {
        $result = $this->facade->formatLastUpdate(0);
        $this->assertEquals('up to date', $result);
    }

    public function testFormatLastUpdateReturnsUpToDateForNegativeDiff(): void
    {
        $result = $this->facade->formatLastUpdate(-1);
        $this->assertEquals('up to date', $result);
    }

    public function testFormatLastUpdateWithSeconds(): void
    {
        $result = $this->facade->formatLastUpdate(30);
        $this->assertEquals('last update: 30 seconds ago', $result);
    }

    public function testFormatLastUpdateWithOneSecond(): void
    {
        $result = $this->facade->formatLastUpdate(1);
        $this->assertEquals('last update: 1 second ago', $result);
    }

    public function testFormatLastUpdateWithMinutes(): void
    {
        $result = $this->facade->formatLastUpdate(120);
        $this->assertEquals('last update: 2 minutes ago', $result);
    }

    public function testFormatLastUpdateWithOneMinute(): void
    {
        $result = $this->facade->formatLastUpdate(60);
        $this->assertEquals('last update: 1 minute ago', $result);
    }

    public function testFormatLastUpdateWithHours(): void
    {
        $result = $this->facade->formatLastUpdate(7200);
        $this->assertEquals('last update: 2 hours ago', $result);
    }

    public function testFormatLastUpdateWithDays(): void
    {
        $result = $this->facade->formatLastUpdate(172800);
        $this->assertEquals('last update: 2 days ago', $result);
    }

    public function testFormatLastUpdateWithWeeks(): void
    {
        $result = $this->facade->formatLastUpdate(1209600);
        $this->assertEquals('last update: 2 weeks ago', $result);
    }

    public function testFormatLastUpdateWithMonths(): void
    {
        $result = $this->facade->formatLastUpdate(5184000);  // ~60 days
        $this->assertEquals('last update: 2 months ago', $result);
    }

    public function testFormatLastUpdateWithYears(): void
    {
        $result = $this->facade->formatLastUpdate(63072000);  // ~2 years
        $this->assertEquals('last update: 2 years ago', $result);
    }

    // ===== Sort options tests =====

    public function testGetSortOptionsReturnsArray(): void
    {
        $result = $this->facade->getSortOptions();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testGetSortOptionsContainsTitleOption(): void
    {
        $result = $this->facade->getSortOptions();

        $this->assertEquals(1, $result[0]['value']);
        $this->assertEquals('Title A-Z', $result[0]['text']);
    }

    public function testGetSortOptionsContainsDateOptions(): void
    {
        $result = $this->facade->getSortOptions();

        $this->assertEquals(2, $result[1]['value']);
        $this->assertEquals('Date Newest First', $result[1]['text']);

        $this->assertEquals(3, $result[2]['value']);
        $this->assertEquals('Date Oldest First', $result[2]['text']);
    }

    public function testGetSortColumnForArticlesReturnsTitle(): void
    {
        $result = $this->facade->getSortColumn(1, 'Fl');
        $this->assertEquals('title', $result);
    }

    public function testGetSortColumnForArticlesReturnsDateDesc(): void
    {
        $result = $this->facade->getSortColumn(2, 'Fl');
        $this->assertEquals('published_at DESC', $result);
    }

    public function testGetSortColumnForArticlesReturnsDateAsc(): void
    {
        $result = $this->facade->getSortColumn(3, 'Fl');
        $this->assertEquals('published_at ASC', $result);
    }

    public function testGetSortColumnForFeedsReturnsName(): void
    {
        $result = $this->facade->getSortColumn(1, 'Nf');
        $this->assertEquals('name', $result);
    }

    public function testGetSortColumnForFeedsReturnsUpdateDesc(): void
    {
        $result = $this->facade->getSortColumn(2, 'Nf');
        $this->assertEquals('update_interval DESC', $result);
    }

    public function testGetSortColumnDefaultsToDateDesc(): void
    {
        $result = $this->facade->getSortColumn(99, 'Fl');
        $this->assertEquals('published_at DESC', $result);
    }

    // ===== Query filter tests =====

    public function testBuildQueryFilterReturnsEmptyForEmptyQuery(): void
    {
        $result = $this->facade->buildQueryFilter('', 'title', '');
        $this->assertIsArray($result);
        $this->assertEquals('', $result['clause']);
        $this->assertEquals('', $result['search']);
    }

    public function testBuildQueryFilterForTitleMode(): void
    {
        $result = $this->facade->buildQueryFilter('test', 'title', '');
        $this->assertIsArray($result);
        $this->assertStringContainsString('title', $result['clause']);
        $this->assertStringContainsString('LIKE', $result['clause']);
        $this->assertEquals('test', $result['search']);
    }

    public function testBuildQueryFilterConvertsWildcards(): void
    {
        $result = $this->facade->buildQueryFilter('test*', 'title', '');
        $this->assertIsArray($result);
        $this->assertEquals('test%', $result['search']);
    }

    public function testBuildQueryFilterForAllFields(): void
    {
        $result = $this->facade->buildQueryFilter('search', 'title,desc,text', '');
        $this->assertIsArray($result);
        $this->assertStringContainsString('title', $result['clause']);
        $this->assertStringContainsString('description', $result['clause']);
        $this->assertStringContainsString('text', $result['clause']);
        $this->assertEquals('search', $result['search']);
    }

    public function testValidateRegexPatternReturnsTrueForEmpty(): void
    {
        $result = $this->facade->validateRegexPattern('');
        $this->assertTrue($result);
    }

    public function testValidateRegexPatternReturnsTrueForValidPattern(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->facade->validateRegexPattern('test.*');
        $this->assertTrue($result);
    }

    public function testValidateRegexPatternReturnsFalseForInvalidPattern(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->facade->validateRegexPattern('[invalid');
        $this->assertFalse($result);
    }

    // ===== Feed load config tests =====

    public function testGetFeedLoadConfigForSingleFeed(): void
    {
        $feed = Feed::reconstitute(
            1,
            1,
            'Config Feed',
            'https://example.com/config.xml',
            '',
            '',
            0,
            'tag=test'
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($feed);

        $result = $this->facade->getFeedLoadConfig(1, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('feeds', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals(1, $result['feeds'][0]['id']);
        $this->assertEquals('Config Feed', $result['feeds'][0]['name']);
    }

    public function testGetFeedLoadConfigForAutoUpdate(): void
    {
        $feed1 = Feed::reconstitute(
            1,
            1,
            'Auto Feed 1',
            'https://example.com/auto1.xml',
            '',
            '',
            0,
            'autoupdate=1h'
        );
        $feed2 = Feed::reconstitute(
            2,
            1,
            'Auto Feed 2',
            'https://example.com/auto2.xml',
            '',
            '',
            0,
            'autoupdate=2h'
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('findNeedingAutoUpdate')
            ->willReturn([$feed1, $feed2]);

        $result = $this->facade->getFeedLoadConfig(0, true);

        $this->assertEquals(2, $result['count']);
        $this->assertCount(2, $result['feeds']);
    }

    public function testGetFeedLoadConfigReturnsEmptyWhenFeedNotFound(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->facade->getFeedLoadConfig(999, false);

        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['feeds']);
    }

    // ===== Languages tests =====

    public function testGetLanguagesReturnsArray(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->facade->getLanguages();
        $this->assertIsArray($result);
    }

    // ===== Feed entity conversion tests =====

    public function testFeedToArrayContainsAllFields(): void
    {
        $feed = Feed::reconstitute(
            1,
            2,
            'Test Feed',
            'https://example.com/feed.xml',
            '//article',
            '//ad',
            1234567890,
            'tag=test,autoupdate=1h'
        );

        $this->feedRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$feed]);

        $result = $this->facade->getFeeds();

        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('language_id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('source_uri', $result[0]);
        $this->assertArrayHasKey('article_section_tags', $result[0]);
        $this->assertArrayHasKey('filter_tags', $result[0]);
        $this->assertArrayHasKey('update_interval', $result[0]);
        $this->assertArrayHasKey('options', $result[0]);

        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(2, $result[0]['language_id']);
        $this->assertEquals('Test Feed', $result[0]['name']);
        $this->assertEquals('//article', $result[0]['article_section_tags']);
        $this->assertEquals('//ad', $result[0]['filter_tags']);
        $this->assertEquals(1234567890, $result[0]['update_interval']);
    }

    // ===== Method existence tests =====

    public function testGetFeedsMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'getFeeds'),
            'getFeeds method should exist'
        );
    }

    public function testGetFeedByIdMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'getFeedById'),
            'getFeedById method should exist'
        );
    }

    public function testCountFeedsMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'countFeeds'),
            'countFeeds method should exist'
        );
    }

    public function testCreateFeedMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'createFeed'),
            'createFeed method should exist'
        );
    }

    public function testUpdateFeedMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'updateFeed'),
            'updateFeed method should exist'
        );
    }

    public function testDeleteFeedsMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'deleteFeeds'),
            'deleteFeeds method should exist'
        );
    }

    public function testGetFeedLinksMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'getFeedLinks'),
            'getFeedLinks method should exist'
        );
    }

    public function testCountFeedLinksMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'countFeedLinks'),
            'countFeedLinks method should exist'
        );
    }

    public function testDeleteArticlesMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'deleteArticles'),
            'deleteArticles method should exist'
        );
    }

    public function testResetUnloadableArticlesMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'resetUnloadableArticles'),
            'resetUnloadableArticles method should exist'
        );
    }

    public function testParseRssFeedMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'parseRssFeed'),
            'parseRssFeed method should exist'
        );
    }

    public function testDetectAndParseFeedMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'detectAndParseFeed'),
            'detectAndParseFeed method should exist'
        );
    }

    public function testExtractTextFromArticleMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'extractTextFromArticle'),
            'extractTextFromArticle method should exist'
        );
    }

    public function testLoadFeedMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'loadFeed'),
            'loadFeed method should exist'
        );
    }

    public function testImportArticlesMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'importArticles'),
            'importArticles method should exist'
        );
    }

    public function testCreateTextFromFeedMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'createTextFromFeed'),
            'createTextFromFeed method should exist'
        );
    }

    public function testSaveTextsFromFeedMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'saveTextsFromFeed'),
            'saveTextsFromFeed method should exist'
        );
    }

    public function testRenderFeedLoadInterfaceModernMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'renderFeedLoadInterfaceModern'),
            'renderFeedLoadInterfaceModern method should exist'
        );
    }

    public function testGetLanguagesMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->facade, 'getLanguages'),
            'getLanguages method should exist'
        );
    }
}
