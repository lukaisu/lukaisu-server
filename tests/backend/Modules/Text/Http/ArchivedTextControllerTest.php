<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Text\Http;

use Lukaisu\Modules\Text\Http\ArchivedTextController;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ArchivedTextController.
 *
 * The archived-list bulk-action handler (archived + handleArchivedMarkAction)
 * was dropped under the headless cut: bulk actions moved to PUT
 * /api/v1/texts/bulk-action (archived scope). Only the single-text delete data
 * route (deleteArchived, DELETE /text/archived/{id}) remains.
 */
class ArchivedTextControllerTest extends TestCase
{
    /** @var TextFacade&MockObject */
    private TextFacade $textService;

    private ArchivedTextController $controller;

    protected function setUp(): void
    {
        if (!defined('LUKAISU_TEST_DB_AVAILABLE') || !LUKAISU_TEST_DB_AVAILABLE) {
            $this->markTestSkipped('Database connection required');
        }
        $this->textService = $this->createMock(TextFacade::class);

        $this->controller = new ArchivedTextController($this->textService);
    }

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(ArchivedTextController::class, $this->controller);
    }

    #[Test]
    public function controllerExtendsBaseController(): void
    {
        $this->assertInstanceOf(BaseController::class, $this->controller);
    }

    #[Test]
    public function deleteArchivedCallsServiceAndReturnsRedirect(): void
    {
        $this->textService->expects($this->once())
            ->method('deleteArchivedText')
            ->with(42);

        $result = $this->controller->deleteArchived(42);
        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('/text/archived', $result->getUrl());
    }

    #[Test]
    public function deleteArchivedReturns302StatusCode(): void
    {
        $this->textService->method('deleteArchivedText');

        $result = $this->controller->deleteArchived(1);
        $this->assertSame(302, $result->getStatusCode());
    }

    #[Test]
    public function deleteArchivedAcceptsIntParameter(): void
    {
        $method = new \ReflectionMethod(ArchivedTextController::class, 'deleteArchived');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('int', $params[0]->getType()->getName());
    }
}
