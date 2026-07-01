<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\Application\UseCases;

use Lukaisu\Modules\Vocabulary\Application\UseCases\CreateStandaloneTerm;
use Lukaisu\Modules\Vocabulary\Application\Services\WordCrudService;
use Lukaisu\Modules\Vocabulary\Application\Services\ExpressionService;
use Lukaisu\Modules\Vocabulary\Application\Services\WordLinkingService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the CreateStandaloneTerm use case.
 *
 * Covers the validation + create-failure branches, which return before any
 * database side effect (the success path links occurrences and touches static
 * DB helpers, so it belongs to the integration suite).
 */
class CreateStandaloneTermTest extends TestCase
{
    /** @var WordCrudService&MockObject */
    private WordCrudService $crudService;

    /** @var ExpressionService&MockObject */
    private ExpressionService $expressionService;

    /** @var WordLinkingService&MockObject */
    private WordLinkingService $linkingService;

    private CreateStandaloneTerm $useCase;

    protected function setUp(): void
    {
        $this->crudService = $this->createMock(WordCrudService::class);
        $this->expressionService = $this->createMock(ExpressionService::class);
        $this->linkingService = $this->createMock(WordLinkingService::class);
        $this->useCase = new CreateStandaloneTerm(
            $this->crudService,
            $this->expressionService,
            $this->linkingService
        );
    }

    public function testZeroLanguageReturnsErrorWithoutCreating(): void
    {
        $this->crudService->expects($this->never())->method('create');

        $result = $this->useCase->execute(0, 'hola', 1, '', '', '', '', null, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testEmptyTextReturnsErrorWithoutCreating(): void
    {
        $this->crudService->expects($this->never())->method('create');

        $result = $this->useCase->execute(1, '   ', 1, '', '', '', '', null, []);

        $this->assertFalse($result['success']);
    }

    public function testInvalidStatusReturnsErrorWithoutCreating(): void
    {
        $this->crudService->expects($this->never())->method('create');

        $result = $this->useCase->execute(1, 'hola', 0, '', '', '', '', null, []);

        $this->assertFalse($result['success']);
    }

    public function testCreateFailurePropagatesErrorBeforeLinking(): void
    {
        $this->crudService->expects($this->once())
            ->method('create')
            ->willReturn([
                'id' => 0,
                'message' => 'Error: Duplicate entry for "x"',
                'success' => false,
                'textlc' => 'x',
                'text' => 'x',
            ]);
        $this->expressionService->expects($this->never())->method('insertExpressions');
        $this->linkingService->expects($this->never())->method('linkToTextItems');

        $result = $this->useCase->execute(1, 'x', 1, '', '', '', '', null, []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Duplicate', (string) $result['error']);
    }
}
