<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Feed\Infrastructure;

use Lukaisu\Modules\Feed\Application\UseCases\DeleteFeeds;
use Lukaisu\Modules\Feed\Application\UseCases\GetFeedById;
use Lukaisu\Modules\Feed\Application\UseCases\UpdateFeed;
use Lukaisu\Modules\Feed\Infrastructure\MySqlArticleRepository;
use Lukaisu\Modules\Feed\Infrastructure\MySqlFeedRepository;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Globals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Multi-user isolation (IDOR) regression tests for the feed repository.
 *
 * In multi-user mode an authenticated user must never be able to read, update
 * or delete another user's feed by id via `GET/PUT/DELETE /api/v1/feeds/{id}`.
 * These exercise the repository/use-case boundary those endpoints funnel
 * through.
 *
 * They hit the real database, so they self-skip when no MySQL is available
 * (the DB-less central gate) per the project's LUKAISU_TEST_DB_AVAILABLE
 * convention. The feed fixture is inserted with foreign-key checks relaxed so
 * we do not need to seed a full users/languages graph just to prove ownership
 * scoping.
 */
#[CoversClass(MySqlFeedRepository::class)]
final class FeedMultiUserIsolationTest extends TestCase
{
    /** Synthetic "user A" — the feed owner. */
    private const OWNER_ID = 900711;

    /** Synthetic "user B" — must not be able to touch user A's feed. */
    private const ATTACKER_ID = 900712;

    private MySqlFeedRepository $feedRepository;

    /** Id of the fixture feed owned by OWNER_ID, or 0 if none/cleaned up. */
    private int $feedId = 0;

    private bool $dbReady = false;

    protected function setUp(): void
    {
        if (!defined('LUKAISU_TEST_DB_AVAILABLE') || !LUKAISU_TEST_DB_AVAILABLE) {
            $this->markTestSkipped('Database connection required');
        }

        $this->feedRepository = new MySqlFeedRepository();

        // Relax FK checks so the fixture does not require a real users/languages
        // graph — we only care that ownership scoping filters the row.
        Connection::execute('SET FOREIGN_KEY_CHECKS=0');
        $this->dbReady = true;

        $this->feedId = (int) Connection::preparedInsert(
            'INSERT INTO news_feeds
                (user_id, language_id, name, source_uri, article_section_tags,
                 filter_tags, update_interval, options)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::OWNER_ID, 1, 'isolation-fixture', 'https://example.test/rss', '', '', 0, '']
        );

        Globals::setMultiUserEnabled(true);
    }

    protected function tearDown(): void
    {
        if ($this->dbReady) {
            if ($this->feedId > 0) {
                Connection::preparedExecute('DELETE FROM news_feeds WHERE id = ?', [$this->feedId]);
            }
            Connection::execute('SET FOREIGN_KEY_CHECKS=1');
        }

        Globals::setMultiUserEnabled(false);
        Globals::setCurrentUserId(null);
        Globals::setCurrentUserIsAdmin(false);
    }

    /** Raw, unscoped existence probe so we can assert what actually survives. */
    private function feedRowExists(int $id): bool
    {
        return Connection::preparedFetchOne('SELECT id FROM news_feeds WHERE id = ?', [$id]) !== null;
    }

    // =========================================================================
    // READ — GET /api/v1/feeds/{id}
    // =========================================================================

    public function testOwnerReadsOwnFeedButAttackerCannot(): void
    {
        Globals::setCurrentUserId(self::OWNER_ID);
        $this->assertNotNull(
            $this->feedRepository->find($this->feedId),
            'Owner must be able to read their own feed'
        );

        Globals::setCurrentUserId(self::ATTACKER_ID);
        $this->assertNull(
            $this->feedRepository->find($this->feedId),
            'A user must NOT be able to read another user\'s feed by id'
        );
    }

    public function testGetFeedByIdUseCaseIsUserScoped(): void
    {
        $useCase = new GetFeedById($this->feedRepository);

        Globals::setCurrentUserId(self::ATTACKER_ID);
        $this->assertNull($useCase->execute($this->feedId));

        Globals::setCurrentUserId(self::OWNER_ID);
        $this->assertNotNull($useCase->execute($this->feedId));
    }

    // =========================================================================
    // UPDATE — PUT /api/v1/feeds/{id}
    // =========================================================================

    public function testAttackerCannotUpdateAnotherUsersFeed(): void
    {
        $useCase = new UpdateFeed($this->feedRepository);

        Globals::setCurrentUserId(self::ATTACKER_ID);
        $result = $useCase->execute(
            $this->feedId,
            1,
            'HIJACKED',
            'https://evil.test/rss'
        );

        $this->assertNull(
            $result,
            'Updating a foreign feed must resolve to "not found" (null)'
        );

        // The owner's row must be byte-for-byte untouched.
        $row = Connection::preparedFetchOne(
            'SELECT name, source_uri FROM news_feeds WHERE id = ?',
            [$this->feedId]
        );
        $this->assertNotNull($row);
        $this->assertSame('isolation-fixture', (string) $row['name']);
        $this->assertSame('https://example.test/rss', (string) $row['source_uri']);
    }

    // =========================================================================
    // DELETE — DELETE /api/v1/feeds/{id}
    // =========================================================================

    public function testAttackerCannotDeleteAnotherUsersFeed(): void
    {
        Globals::setCurrentUserId(self::ATTACKER_ID);

        $this->assertFalse($this->feedRepository->delete($this->feedId));
        $this->assertSame(0, $this->feedRepository->deleteMultiple([$this->feedId]));
        $this->assertTrue(
            $this->feedRowExists($this->feedId),
            'A foreign feed must survive an attacker\'s delete attempt'
        );
    }

    public function testDeleteFeedsUseCaseIsUserScoped(): void
    {
        $useCase = new DeleteFeeds($this->feedRepository, new MySqlArticleRepository());

        Globals::setCurrentUserId(self::ATTACKER_ID);
        $result = $useCase->execute([$this->feedId]);

        $this->assertSame(0, $result['feeds'], 'Attacker must delete 0 feeds');
        $this->assertTrue($this->feedRowExists($this->feedId));
    }

    public function testOwnerCanDeleteOwnFeed(): void
    {
        Globals::setCurrentUserId(self::OWNER_ID);

        $this->assertTrue($this->feedRepository->delete($this->feedId));
        $this->assertFalse($this->feedRowExists($this->feedId));

        // Already removed — don't try to delete it again in tearDown.
        $this->feedId = 0;
    }
}
