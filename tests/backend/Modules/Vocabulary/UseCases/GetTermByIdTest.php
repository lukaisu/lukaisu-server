<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\UseCases;

use DateTimeImmutable;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Vocabulary\Application\UseCases\GetTermById;
use Lukaisu\Modules\Vocabulary\Domain\Term;
use Lukaisu\Modules\Vocabulary\Domain\TermRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the GetTermById use case.
 */
class GetTermByIdTest extends TestCase
{
    /** @var TermRepositoryInterface&MockObject */
    private TermRepositoryInterface $repository;
    private GetTermById $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        Globals::reset();
        $this->repository = $this->createMock(TermRepositoryInterface::class);
        $this->useCase = new GetTermById($this->repository);
    }

    protected function tearDown(): void
    {
        Globals::reset();
        parent::tearDown();
    }

    private function createTestTerm(int $id = 42): Term
    {
        return Term::reconstitute(
            $id,
            1,
            'TestWord',
            'testword',
            'test',
            'test',
            3,
            'Translation',
            'Example sentence',
            'Some notes',
            'romanization',
            1,
            new DateTimeImmutable('2024-01-15 10:30:00'),
            new DateTimeImmutable('2024-01-16 11:00:00'),
            0.5,
            0.7,
            0.3
        );
    }

    // =========================================================================
    // execute() Tests
    // =========================================================================

    public function testExecuteReturnsTermWhenFound(): void
    {
        $term = $this->createTestTerm();

        $this->repository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($term);

        $result = $this->useCase->execute(42);

        $this->assertInstanceOf(Term::class, $result);
        $this->assertEquals(42, $result->id()->toInt());
        $this->assertEquals('TestWord', $result->text());
    }

    public function testExecuteReturnsNullWhenNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->useCase->execute(999);

        $this->assertNull($result);
    }

    public function testExecuteReturnsNullForZeroId(): void
    {
        $this->repository->expects($this->never())->method('find');

        $result = $this->useCase->execute(0);

        $this->assertNull($result);
    }

    public function testExecuteReturnsNullForNegativeId(): void
    {
        $this->repository->expects($this->never())->method('find');

        $result = $this->useCase->execute(-1);

        $this->assertNull($result);
    }

    // =========================================================================
    // executeAsArray() Tests
    // =========================================================================

    public function testExecuteAsArrayReturnsArrayWhenFound(): void
    {
        $term = $this->createTestTerm();

        $this->repository->method('find')->willReturn($term);

        $result = $this->useCase->executeAsArray(42);

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['id']);
        $this->assertEquals(1, $result['language_id']);
        $this->assertEquals('TestWord', $result['text']);
        $this->assertEquals('testword', $result['text_lc']);
        $this->assertEquals(3, $result['status']);
        $this->assertEquals('Translation', $result['translation']);
        $this->assertEquals('Example sentence', $result['sentence']);
        $this->assertEquals('Some notes', $result['notes']);
        $this->assertEquals('romanization', $result['romanization']);
        $this->assertEquals(1, $result['word_count']);
        $this->assertEquals('2024-01-15 10:30:00', $result['created_at']);
        $this->assertEquals('2024-01-16 11:00:00', $result['status_changed_at']);
        $this->assertEquals(0.5, $result['today_score']);
        $this->assertEquals(0.7, $result['tomorrow_score']);
        $this->assertEquals(0.3, $result['random']);
    }

    public function testExecuteAsArrayReturnsNullWhenNotFound(): void
    {
        $this->repository->method('find')->willReturn(null);

        $result = $this->useCase->executeAsArray(999);

        $this->assertNull($result);
    }

    public function testExecuteAsArrayReturnsNullForZeroId(): void
    {
        $result = $this->useCase->executeAsArray(0);

        $this->assertNull($result);
    }

    public function testExecuteAsArrayReturnsNullForNegativeId(): void
    {
        $result = $this->useCase->executeAsArray(-5);

        $this->assertNull($result);
    }

    public function testExecuteAsArrayContainsAllExpectedKeys(): void
    {
        $term = $this->createTestTerm();
        $this->repository->method('find')->willReturn($term);

        $result = $this->useCase->executeAsArray(42);

        $expectedKeys = [
            'id',
            'language_id',
            'text',
            'text_lc',
            'status',
            'translation',
            'sentence',
            'notes',
            'romanization',
            'word_count',
            'created_at',
            'status_changed_at',
            'today_score',
            'tomorrow_score',
            'random',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
