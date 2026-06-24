<?php

declare(strict_types=1);

namespace Tests\Modules\Feed\Infrastructure;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lukaisu\Modules\Feed\Infrastructure\MySqlArticleRepository;
use Lukaisu\Modules\Feed\Domain\Article;

/**
 * Tests for MySqlArticleRepository.
 *
 */
#[CoversClass(MySqlArticleRepository::class)]
class MySqlArticleRepositoryTest extends TestCase
{
    private MySqlArticleRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new MySqlArticleRepository();
    }

    // =========================================================================
    // Configuration Property Tests
    // =========================================================================

    public function testTableNameProperty(): void
    {
        $reflection = new \ReflectionProperty(MySqlArticleRepository::class, 'tableName');

        $this->assertSame('feed_links', $reflection->getValue($this->repository));
    }

    public function testPrimaryKeyProperty(): void
    {
        $reflection = new \ReflectionProperty(MySqlArticleRepository::class, 'primaryKey');

        $this->assertSame('id', $reflection->getValue($this->repository));
    }

    public function testColumnMapProperty(): void
    {
        $reflection = new \ReflectionProperty(MySqlArticleRepository::class, 'columnMap');

        $columnMap = $reflection->getValue($this->repository);

        $this->assertIsArray($columnMap);
        $this->assertSame('id', $columnMap['id']);
        $this->assertSame('feed_id', $columnMap['feedId']);
        $this->assertSame('title', $columnMap['title']);
        $this->assertSame('link', $columnMap['link']);
        $this->assertSame('description', $columnMap['description']);
        $this->assertSame('published_at', $columnMap['date']);
        $this->assertSame('audio', $columnMap['audio']);
        $this->assertSame('text', $columnMap['text']);
    }

    // =========================================================================
    // mapToEntity() Tests via Reflection
    // =========================================================================

    public function testMapToEntityCreatesArticle(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $row = [
            'id' => '123',
            'feed_id' => '456',
            'title' => 'Test Article',
            'link' => 'https://example.com/article',
            'description' => 'Test description',
            'published_at' => '2024-01-15 10:30:00',
            'audio' => 'https://example.com/audio.mp3',
            'text' => 'Full article text',
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame(123, $article->id());
        $this->assertSame(456, $article->feedId());
        $this->assertSame('Test Article', $article->title());
        $this->assertSame('https://example.com/article', $article->link());
        $this->assertSame('Test description', $article->description());
        $this->assertSame('2024-01-15 10:30:00', $article->date());
        $this->assertSame('https://example.com/audio.mp3', $article->audio());
        $this->assertSame('Full article text', $article->text());
    }

    public function testMapToEntityWithNullOptionalFields(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $row = [
            'id' => '1',
            'feed_id' => '2',
            'title' => 'Title',
            'link' => 'https://example.com',
            'description' => null,
            'published_at' => null,
            'audio' => null,
            'text' => null,
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertSame('', $article->description());
        $this->assertSame('', $article->date());
        $this->assertSame('', $article->audio());
        $this->assertSame('', $article->text());
    }

    public function testMapToEntityWithMissingOptionalFields(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $row = [
            'id' => '1',
            'feed_id' => '2',
            'title' => 'Title',
            'link' => 'https://example.com',
            // Missing: description, published_at, audio, text
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertSame('', $article->description());
        $this->assertSame('', $article->date());
        $this->assertSame('', $article->audio());
        $this->assertSame('', $article->text());
    }

    public function testMapToEntityConvertsStringIdsToInt(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $row = [
            'id' => '999',
            'feed_id' => '888',
            'title' => 'Title',
            'link' => 'https://example.com',
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertSame(999, $article->id());
        $this->assertSame(888, $article->feedId());
    }

    public function testMapToEntityWithUnicodeContent(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $row = [
            'id' => '1',
            'feed_id' => '2',
            'title' => '日本語タイトル',
            'link' => 'https://example.com/日本語',
            'description' => 'Descripción en español',
            'published_at' => '2024-01-15',
            'audio' => '',
            'text' => 'Текст на русском',
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertSame('日本語タイトル', $article->title());
        $this->assertSame('Descripción en español', $article->description());
        $this->assertSame('Текст на русском', $article->text());
    }

    // =========================================================================
    // mapToRow() Tests via Reflection
    // =========================================================================

    public function testMapToRowConvertsArticleToArray(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToRow');

        $article = Article::reconstitute(
            123,
            456,
            'Test Title',
            'https://example.com',
            'Description',
            '2024-01-15',
            'https://example.com/audio.mp3',
            'Full text'
        );

        $row = $method->invoke($this->repository, $article);

        $this->assertIsArray($row);
        $this->assertSame(456, $row['feed_id']);
        $this->assertSame('Test Title', $row['title']);
        $this->assertSame('https://example.com', $row['link']);
        $this->assertSame('Description', $row['description']);
        $this->assertSame('2024-01-15', $row['published_at']);
        $this->assertSame('https://example.com/audio.mp3', $row['audio']);
        $this->assertSame('Full text', $row['text']);
    }

    public function testMapToRowDoesNotIncludeId(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToRow');

        $article = Article::reconstitute(123, 1, 'Title', 'link', '', '', '', '');

        $row = $method->invoke($this->repository, $article);

        $this->assertArrayNotHasKey('id', $row);
        $this->assertArrayNotHasKey('id', $row);
    }

    public function testMapToRowWithEmptyOptionalFields(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToRow');

        $article = Article::reconstitute(1, 2, 'Title', 'link', '', '', '', '');

        $row = $method->invoke($this->repository, $article);

        $this->assertSame('', $row['description']);
        $this->assertSame('', $row['published_at']);
        $this->assertSame('', $row['audio']);
        $this->assertSame('', $row['text']);
    }

    // =========================================================================
    // getEntityId() Tests via Reflection
    // =========================================================================

    public function testGetEntityIdReturnsArticleId(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'getEntityId');

        $article = Article::reconstitute(999, 1, 'Title', 'link', '', '', '', '');

        $id = $method->invoke($this->repository, $article);

        $this->assertSame(999, $id);
    }

    public function testGetEntityIdReturnsZeroForNewArticle(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'getEntityId');

        $article = Article::create(1, 'Title', 'link');

        $id = $method->invoke($this->repository, $article);

        $this->assertSame(0, $id);
    }

    // =========================================================================
    // setEntityId() Tests via Reflection
    // =========================================================================

    public function testSetEntityIdSetsArticleId(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'setEntityId');

        $article = Article::create(1, 'Title', 'link');
        $this->assertNull($article->id());

        $method->invoke($this->repository, $article, 123);

        $this->assertSame(123, $article->id());
    }

    // =========================================================================
    // findByIds() with Empty Array
    // =========================================================================

    public function testFindByIdsWithEmptyArrayReturnsEmpty(): void
    {
        $result = $this->repository->findByIds([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // deleteByFeeds() with Empty Array
    // =========================================================================

    public function testDeleteByFeedsWithEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->deleteByFeeds([]);

        $this->assertSame(0, $result);
    }

    // =========================================================================
    // deleteByIds() with Empty Array
    // =========================================================================

    public function testDeleteByIdsWithEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->deleteByIds([]);

        $this->assertSame(0, $result);
    }

    // =========================================================================
    // resetErrorsByFeeds() with Empty Array
    // =========================================================================

    public function testResetErrorsByFeedsWithEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->resetErrorsByFeeds([]);

        $this->assertSame(0, $result);
    }

    // =========================================================================
    // countByFeeds() with Empty Array
    // =========================================================================

    public function testCountByFeedsWithEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->countByFeeds([]);

        $this->assertSame(0, $result);
    }

    // =========================================================================
    // findByFeedsWithStatus() with Empty Array
    // =========================================================================

    public function testFindByFeedsWithStatusWithEmptyArrayReturnsEmpty(): void
    {
        $result = $this->repository->findByFeedsWithStatus([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // Roundtrip Tests (mapToEntity -> mapToRow)
    // =========================================================================

    public function testMapRoundtrip(): void
    {
        $mapToEntity = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');
        $mapToRow = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToRow');

        $originalRow = [
            'id' => '123',
            'feed_id' => '456',
            'title' => 'Test Article',
            'link' => 'https://example.com/article',
            'description' => 'Test description',
            'published_at' => '2024-01-15 10:30:00',
            'audio' => 'https://example.com/audio.mp3',
            'text' => 'Full article text',
        ];

        $article = $mapToEntity->invoke($this->repository, $originalRow);
        $resultRow = $mapToRow->invoke($this->repository, $article);

        // Verify all fields except ID are preserved
        $this->assertSame(456, $resultRow['feed_id']);
        $this->assertSame('Test Article', $resultRow['title']);
        $this->assertSame('https://example.com/article', $resultRow['link']);
        $this->assertSame('Test description', $resultRow['description']);
        $this->assertSame('2024-01-15 10:30:00', $resultRow['published_at']);
        $this->assertSame('https://example.com/audio.mp3', $resultRow['audio']);
        $this->assertSame('Full article text', $resultRow['text']);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testMapToEntityWithErrorMarkedLink(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $row = [
            'id' => '1',
            'feed_id' => '2',
            'title' => 'Error Article',
            'link' => ' https://example.com', // Space prefix indicates error
            'description' => '',
            'published_at' => '',
            'audio' => '',
            'text' => '',
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertTrue($article->hasError());
        $this->assertStringStartsWith(' ', $article->link());
    }

    public function testMapToEntityWithLongValues(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $longText = str_repeat('a', 10000);
        $row = [
            'id' => '1',
            'feed_id' => '2',
            'title' => 'Title',
            'link' => 'https://example.com',
            'description' => $longText,
            'published_at' => '',
            'audio' => '',
            'text' => $longText,
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertSame($longText, $article->description());
        $this->assertSame($longText, $article->text());
    }

    public function testMapToEntityWithSpecialCharacters(): void
    {
        $method = new \ReflectionMethod(MySqlArticleRepository::class, 'mapToEntity');

        $row = [
            'id' => '1',
            'feed_id' => '2',
            'title' => "Title with 'quotes' and \"double quotes\"",
            'link' => 'https://example.com?a=1&b=2',
            'description' => '<script>alert("xss")</script>',
            'published_at' => '',
            'audio' => '',
            'text' => "Line1\nLine2\tTabbed",
        ];

        $article = $method->invoke($this->repository, $row);

        $this->assertSame("Title with 'quotes' and \"double quotes\"", $article->title());
        $this->assertSame('https://example.com?a=1&b=2', $article->link());
        $this->assertStringContainsString('<script>', $article->description());
        $this->assertStringContainsString("\n", $article->text());
    }
}
