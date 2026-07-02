<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Text\Http;

use Lukaisu\Modules\Text\Http\TextCrudController;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TextCrudController.
 *
 * The texts-list marked-action handler (edit + handleMarkAction +
 * handleTextOperation + handleAutoSplitImport + showTextsList) was dropped under
 * the headless cut: bulk actions moved to PUT /api/v1/texts/bulk-action. Only
 * the single-text delete/archive/unarchive data routes remain.
 */
class TextCrudControllerTest extends TestCase
{
    /** @var TextFacade&MockObject */
    private TextFacade $textService;

    private TextCrudController $controller;

    protected function setUp(): void
    {
        $this->textService = $this->createMock(TextFacade::class);

        $this->controller = new TextCrudController($this->textService);
    }

    #[Test]
    public function classExtendsBaseController(): void
    {
        $reflection = new \ReflectionClass(TextCrudController::class);
        $this->assertSame(
            'Lukaisu\Shared\Http\BaseController',
            $reflection->getParentClass()->getName()
        );
    }

    #[Test]
    public function constructorAcceptsMocks(): void
    {
        $this->assertInstanceOf(TextCrudController::class, $this->controller);
    }

    #[Test]
    public function deleteCallsTextServiceAndRedirects(): void
    {
        $this->textService->expects($this->once())
            ->method('deleteText')
            ->with(42);

        $result = $this->controller->delete(42);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('/texts', $result->getUrl());
    }

    #[Test]
    public function archiveCallsTextServiceAndRedirects(): void
    {
        $this->textService->expects($this->once())
            ->method('archiveText')
            ->with(17);

        $result = $this->controller->archive(17);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('/texts', $result->getUrl());
    }

    #[Test]
    public function unarchiveCallsTextServiceAndRedirects(): void
    {
        $this->textService->expects($this->once())
            ->method('unarchiveText')
            ->with(33);

        $result = $this->controller->unarchive(33);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('/text/archived', $result->getUrl());
    }

    #[Test]
    public function unarchiveRedirectsToArchivedList(): void
    {
        $this->textService->expects($this->once())
            ->method('unarchiveText')
            ->with(1);

        $result = $this->controller->unarchive(1);

        $this->assertNotSame('/texts', $result->getUrl());
        $this->assertSame('/text/archived', $result->getUrl());
    }
}
