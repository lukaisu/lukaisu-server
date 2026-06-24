<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Services;

use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Feed\Application\FeedFacade;
use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Modules\Feed\FeedServiceProvider;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the FeedFacade class.
 *
 * Tests feed/newsfeed CRUD operations through the facade layer.
 * Migrated from FeedServiceTest to use the new modular architecture.
 */
class FeedServiceTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $testLangId = 0;
    private FeedFacade $service;

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

        if (self::$dbConnected) {
            // Create a test language if it doesn't exist
            $langTable = Globals::table('languages');
            $existingLang = Connection::fetchValue(
                "SELECT LgID AS value FROM $langTable WHERE LgName = 'FeedServiceTestLang' LIMIT 1"
            );

            if ($existingLang) {
                self::$testLangId = (int)$existingLang;
            } else {
                Connection::query(
                    "INSERT INTO $langTable (LgName, LgDict1URI, LgDict2URI, LgGoogleTranslateURI, " .
                    "LgTextSize, LgCharacterSubstitutions, LgRegexpSplitSentences, " .
                    "LgExceptionsSplitSentences, LgRegexpWordCharacters, LgRemoveSpaces, LgSplitEachChar, " .
                    "LgRightToLeft, LgShowRomanization) VALUES ('FeedServiceTestLang', 'http://test.com/###', " .
                    "'', 'http://translate.test/###', 100, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 1)"
                );
                self::$testLangId = (int)Connection::fetchValue(
                    "SELECT LAST_INSERT_ID() AS value"
                );
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test feeds and feed_links
        $feedLinksTable = Globals::table('feed_links');
        $newsFeedsTable = Globals::table('news_feeds');
        $langTable = Globals::table('languages');
        Connection::query(
            "DELETE FROM $feedLinksTable WHERE feed_id IN " .
            "(SELECT id FROM $newsFeedsTable WHERE name LIKE 'Test Feed%')"
        );
        Connection::query("DELETE FROM $newsFeedsTable WHERE name LIKE 'Test Feed%'");
        Connection::query("DELETE FROM $langTable WHERE LgName = 'FeedServiceTestLang'");
    }

    protected function setUp(): void
    {
        // Register FeedServiceProvider if not already registered
        $container = Container::getInstance();
        $provider = new FeedServiceProvider();
        $provider->register($container);
        $provider->boot($container);

        $this->service = $container->get(FeedFacade::class);
    }

    protected function tearDown(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test feeds after each test
        $feedLinksTable = Globals::table('feed_links');
        $newsFeedsTable = Globals::table('news_feeds');
        Connection::query(
            "DELETE FROM $feedLinksTable WHERE feed_id IN " .
            "(SELECT id FROM $newsFeedsTable WHERE name LIKE 'Test Feed%')"
        );
        Connection::query("DELETE FROM $newsFeedsTable WHERE name LIKE 'Test Feed%'");
    }

    // ===== getFeeds() tests =====

    public function testGetFeedsReturnsEmptyArrayWhenNoFeeds(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feeds = $this->service->getFeeds(self::$testLangId);
        $this->assertIsArray($feeds);
    }

    public function testGetFeedsReturnsCreatedFeed(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a feed
        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed GetFeeds',
            'source_uri' => 'https://example.com/rss',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        $feeds = $this->service->getFeeds(self::$testLangId);

        $this->assertNotEmpty($feeds);
        $foundFeed = array_filter($feeds, fn($f) => (int)$f['id'] === $feedId);
        $this->assertNotEmpty($foundFeed);
    }

    public function testGetFeedsFiltersOnLanguageId(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a feed for our test language
        $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed Language Filter',
            'source_uri' => 'https://example.com/rss',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        // Get feeds for a non-existent language
        $feeds = $this->service->getFeeds(99999);

        $foundTestFeed = array_filter($feeds, fn($f) => $f['name'] === 'Test Feed Language Filter');
        $this->assertEmpty($foundTestFeed);
    }

    // ===== getFeedById() tests =====

    public function testGetFeedByIdReturnsCorrectFeed(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed GetById',
            'source_uri' => 'https://example.com/feed',
            'article_section_tags' => 'item',
            'filter_tags' => '',
            'options' => 'autoupdate=2h',
        ]);

        $feed = $this->service->getFeedById($feedId);

        $this->assertIsArray($feed);
        $this->assertEquals('Test Feed GetById', $feed['name']);
        $this->assertEquals('https://example.com/feed', $feed['source_uri']);
        $this->assertEquals('autoupdate=2h', $feed['options']);
    }

    public function testGetFeedByIdReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feed = $this->service->getFeedById(999999);
        $this->assertNull($feed);
    }

    // ===== createFeed() tests =====

    public function testCreateFeedReturnsNewFeedId(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed Create',
            'source_uri' => 'https://example.com/new-feed',
            'article_section_tags' => 'entry',
            'filter_tags' => 'div.content',
            'options' => 'max_texts=10',
        ]);

        $this->assertIsInt($feedId);
        $this->assertGreaterThan(0, $feedId);

        // Verify it was created
        $feed = $this->service->getFeedById($feedId);
        $this->assertEquals('Test Feed Create', $feed['name']);
    }

    public function testCreateFeedSavesAllFields(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed AllFields',
            'source_uri' => 'https://example.com/all-fields',
            'article_section_tags' => 'content',
            'filter_tags' => 'div.filter',
            'options' => 'autoupdate=1d,max_texts=5',
        ]);

        $feed = $this->service->getFeedById($feedId);

        $this->assertEquals((string)self::$testLangId, $feed['language_id']);
        $this->assertEquals('Test Feed AllFields', $feed['name']);
        $this->assertEquals('https://example.com/all-fields', $feed['source_uri']);
        $this->assertEquals('content', $feed['article_section_tags']);
        $this->assertEquals('div.filter', $feed['filter_tags']);
        $this->assertEquals('autoupdate=1d,max_texts=5', $feed['options']);
    }

    // ===== updateFeed() tests =====

    public function testUpdateFeedModifiesExistingFeed(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed Update Original',
            'source_uri' => 'https://example.com/original',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        $this->service->updateFeed($feedId, [
            'language_id' => self::$testLangId,
            'name' => 'Test Feed Update Modified',
            'source_uri' => 'https://example.com/modified',
            'article_section_tags' => 'section',
            'filter_tags' => 'div.new-filter',
            'options' => 'autoupdate=1w',
        ]);

        $feed = $this->service->getFeedById($feedId);

        $this->assertEquals('Test Feed Update Modified', $feed['name']);
        $this->assertEquals('https://example.com/modified', $feed['source_uri']);
        $this->assertEquals('section', $feed['article_section_tags']);
        $this->assertEquals('div.new-filter', $feed['filter_tags']);
        $this->assertEquals('autoupdate=1w', $feed['options']);
    }

    // ===== deleteFeeds() tests =====

    public function testDeleteFeedsRemovesFeedAndArticles(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed Delete',
            'source_uri' => 'https://example.com/delete',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        // Add a feedlink (article) to the feed
        $feedLinksTable = Globals::table('feed_links');
        $timestamp = time();
        Connection::execute(
            "INSERT INTO $feedLinksTable (feed_id, title, link, description, published_at)
             VALUES ($feedId, 'Test Article', 'https://example.com/article', 'Description', " .
            "FROM_UNIXTIME($timestamp))"
        );

        // Verify article exists
        $count = (int)Connection::fetchValue(
            "SELECT COUNT(*) AS value FROM " . Globals::table('feed_links') . " WHERE feed_id = $feedId"
        );
        $this->assertEquals(1, $count);

        // Delete the feed
        $result = $this->service->deleteFeeds((string)$feedId);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['feeds']);
        $this->assertEquals(1, $result['articles']);

        // Verify feed is gone
        $this->assertNull($this->service->getFeedById($feedId));
    }

    // ===== countFeeds() tests =====

    public function testCountFeedsReturnsCorrectCount(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Get initial count
        $initialCount = $this->service->countFeeds(self::$testLangId);

        // Create a feed
        $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed Count',
            'source_uri' => 'https://example.com/count',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        // Check count increased
        $newCount = $this->service->countFeeds(self::$testLangId);
        $this->assertEquals($initialCount + 1, $newCount);
    }

    // ===== parseAutoUpdateInterval() tests =====

    public function testParseAutoUpdateIntervalHours(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->parseAutoUpdateInterval('2h');
        $this->assertEquals(2 * 60 * 60, $result);
    }

    public function testParseAutoUpdateIntervalDays(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->parseAutoUpdateInterval('3d');
        $this->assertEquals(3 * 60 * 60 * 24, $result);
    }

    public function testParseAutoUpdateIntervalWeeks(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->parseAutoUpdateInterval('1w');
        $this->assertEquals(1 * 60 * 60 * 24 * 7, $result);
    }

    public function testParseAutoUpdateIntervalInvalid(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // String without h, d, or w returns null
        $result = $this->service->parseAutoUpdateInterval('xyz');
        $this->assertNull($result);
    }

    // ===== formatLastUpdate() tests =====

    public function testFormatLastUpdateUpToDate(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->formatLastUpdate(0);
        $this->assertEquals('up to date', $result);
    }

    public function testFormatLastUpdateMinutes(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->formatLastUpdate(120); // 2 minutes
        $this->assertEquals('last update: 2 minutes ago', $result);
    }

    public function testFormatLastUpdateSingularMinute(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->formatLastUpdate(65); // 1 minute
        $this->assertEquals('last update: 1 minute ago', $result);
    }

    public function testFormatLastUpdateHours(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->formatLastUpdate(7200); // 2 hours
        $this->assertEquals('last update: 2 hours ago', $result);
    }

    public function testFormatLastUpdateDays(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->formatLastUpdate(172800); // 2 days
        $this->assertEquals('last update: 2 days ago', $result);
    }

    // ===== buildQueryFilter() tests =====

    public function testBuildQueryFilterReturnsEmptyForEmptyQuery(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->buildQueryFilter('', 'title,desc,text', '');
        $this->assertIsArray($result);
        $this->assertEquals('', $result['clause']);
        $this->assertEquals('', $result['search']);
    }

    public function testBuildQueryFilterCreatesLikeClause(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->buildQueryFilter('test*', 'title,desc,text', '');
        $this->assertIsArray($result);
        $this->assertStringContainsString('LIKE', $result['clause']);
        $this->assertStringContainsString('title', $result['clause']);
        $this->assertStringContainsString('description', $result['clause']);
        $this->assertStringContainsString('text', $result['clause']);
        $this->assertEquals('test%', $result['search']);
    }

    public function testBuildQueryFilterTitleOnly(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->buildQueryFilter('test', 'title', '');
        $this->assertIsArray($result);
        $this->assertStringContainsString('title', $result['clause']);
        $this->assertStringNotContainsString('description', $result['clause']);
        $this->assertStringNotContainsString('text', $result['clause']);
        $this->assertEquals('test', $result['search']);
    }

    // ===== validateRegexPattern() tests =====

    public function testValidateRegexPatternEmptyIsValid(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->validateRegexPattern('');
        $this->assertTrue($result);
    }

    public function testValidateRegexPatternValidPattern(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->validateRegexPattern('test.*pattern');
        $this->assertTrue($result);
    }

    public function testValidateRegexPatternInvalidPattern(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Unmatched bracket is invalid regex
        $result = $this->service->validateRegexPattern('[invalid');
        $this->assertFalse($result);
    }

    // ===== getLanguages() tests =====

    public function testGetLanguagesReturnsArray(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $languages = $this->service->getLanguages();

        $this->assertIsArray($languages);
        // Should contain our test language
        $foundTestLang = array_filter($languages, fn($l) => $l['LgName'] === 'FeedServiceTestLang');
        $this->assertNotEmpty($foundTestLang);
    }

    // ===== getSortOptions() tests =====

    public function testGetSortOptionsReturnsExpectedOptions(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $options = $this->service->getSortOptions();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);

        $this->assertEquals(1, $options[0]['value']);
        $this->assertEquals('Title A-Z', $options[0]['text']);

        $this->assertEquals(2, $options[1]['value']);
        $this->assertEquals('Date Newest First', $options[1]['text']);

        $this->assertEquals(3, $options[2]['value']);
        $this->assertEquals('Date Oldest First', $options[2]['text']);
    }

    // ===== getSortColumn() tests =====

    public function testGetSortColumnFeedlinks(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->assertEquals('title', $this->service->getSortColumn(1, 'Fl'));
        $this->assertEquals('published_at DESC', $this->service->getSortColumn(2, 'Fl'));
        $this->assertEquals('published_at ASC', $this->service->getSortColumn(3, 'Fl'));
    }

    public function testGetSortColumnNewsfeeds(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->assertEquals('name', $this->service->getSortColumn(1, 'Nf'));
        $this->assertEquals('update_interval DESC', $this->service->getSortColumn(2, 'Nf'));
        $this->assertEquals('update_interval ASC', $this->service->getSortColumn(3, 'Nf'));
    }

    public function testGetSortColumnDefaultsToDateDesc(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->assertEquals('published_at DESC', $this->service->getSortColumn(99, 'Fl'));
    }

    // ===== deleteArticles() tests =====

    public function testDeleteArticlesRemovesArticlesFromFeed(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed DeleteArticles',
            'source_uri' => 'https://example.com/delete-articles',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        // Add articles
        for ($i = 1; $i <= 3; $i++) {
            Connection::execute(
                "INSERT INTO " . Globals::table('feed_links') . " (feed_id, title, link, description, published_at)
                 VALUES ($feedId, 'Article $i', 'https://example.com/art$i', 'Desc', FROM_UNIXTIME(" . time() . "))"
            );
        }

        // Verify articles exist
        $count = (int)Connection::fetchValue(
            "SELECT COUNT(*) AS value FROM " . Globals::table('feed_links') . " WHERE feed_id = $feedId"
        );
        $this->assertEquals(3, $count);

        // Delete articles
        $deleted = $this->service->deleteArticles((string)$feedId);
        $this->assertEquals(3, $deleted);

        // Verify articles are gone
        $count = (int)Connection::fetchValue(
            "SELECT COUNT(*) AS value FROM " . Globals::table('feed_links') . " WHERE feed_id = $feedId"
        );
        $this->assertEquals(0, $count);

        // Verify feed still exists
        $this->assertNotNull($this->service->getFeedById($feedId));
    }

    // ===== resetUnloadableArticles() tests =====

    public function testResetUnloadableArticlesTrimsLinks(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed ResetUnloadable',
            'source_uri' => 'https://example.com/reset',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        // Add article with space prefix (unloadable)
        $feedLinksTable = Globals::table('feed_links');
        $timestamp = time();
        Connection::execute(
            "INSERT INTO $feedLinksTable (feed_id, title, link, description, published_at)
             VALUES ($feedId, 'Unloadable Article', ' https://example.com/unloadable', 'Desc', " .
            "FROM_UNIXTIME($timestamp))"
        );

        // Reset unloadable
        $count = $this->service->resetUnloadableArticles((string)$feedId);
        $this->assertEquals(1, $count);

        // Verify link is trimmed
        $link = Connection::fetchValue(
            "SELECT link AS value FROM " . Globals::table('feed_links') . " WHERE feed_id = $feedId"
        );
        $this->assertEquals('https://example.com/unloadable', $link);
    }

    // ===== Integration test =====

    public function testCreateUpdateDeleteFeedRoundTrip(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create
        $feedId = $this->service->createFeed([
            'language_id' => self::$testLangId,
            'name' => 'Test Feed RoundTrip',
            'source_uri' => 'https://example.com/roundtrip',
            'article_section_tags' => 'article',
            'filter_tags' => '',
            'options' => '',
        ]);

        $this->assertGreaterThan(0, $feedId);

        // Read
        $feed = $this->service->getFeedById($feedId);
        $this->assertEquals('Test Feed RoundTrip', $feed['name']);

        // Update
        $this->service->updateFeed($feedId, [
            'language_id' => self::$testLangId,
            'name' => 'Test Feed RoundTrip Updated',
            'source_uri' => 'https://example.com/roundtrip-updated',
            'article_section_tags' => 'section',
            'filter_tags' => 'div.filter',
            'options' => 'autoupdate=1d',
        ]);

        $updatedFeed = $this->service->getFeedById($feedId);
        $this->assertEquals('Test Feed RoundTrip Updated', $updatedFeed['name']);

        // Delete
        $result = $this->service->deleteFeeds((string)$feedId);
        $this->assertEquals(1, $result['feeds']);

        // Verify deleted
        $this->assertNull($this->service->getFeedById($feedId));
    }
}
