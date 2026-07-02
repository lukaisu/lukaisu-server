<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Text\Http;

use Lukaisu\Modules\Text\Http\TextController;
use Lukaisu\Modules\Text\Http\TextCrudController;
use Lukaisu\Modules\Text\Http\ArchivedTextController;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TextController (facade) and sub-controllers.
 *
 * Tests text management, display, archive/unarchive, mark actions, class
 * structure, and method signatures. The reader (`read`) and parse-preview
 * (`check`) render paths were retired under the headless cut (Phase R) — the
 * bundle serves those GETs and posts parse-check to /api/v1/texts/check.
 */
class TextControllerTest extends TestCase
{
    /** @var TextFacade&MockObject */
    private TextFacade $textService;

    private TextController $controller;
    private TextCrudController $crudController;
    private ArchivedTextController $archivedController;

    protected function setUp(): void
    {
        if (!defined('LUKAISU_TEST_DB_AVAILABLE') || !LUKAISU_TEST_DB_AVAILABLE) {
            $this->markTestSkipped('Database connection required');
        }
        $this->textService = $this->createMock(TextFacade::class);

        $this->controller = new TextController($this->textService);
        $this->crudController = new TextCrudController($this->textService);
        $this->archivedController = new ArchivedTextController($this->textService);
    }

    // =========================================================================
    // Constructor tests
    // =========================================================================

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(TextController::class, $this->controller);
    }

    #[Test]
    public function constructorAcceptsNullParameters(): void
    {
        $controller = new TextController(null);
        $this->assertInstanceOf(TextController::class, $controller);
    }

    #[Test]
    public function constructorCreatesSubControllers(): void
    {
        $reflection = new \ReflectionProperty(TextController::class, 'crudController');
        $this->assertInstanceOf(TextCrudController::class, $reflection->getValue($this->controller));

        $reflection = new \ReflectionProperty(TextController::class, 'archivedController');
        $this->assertInstanceOf(ArchivedTextController::class, $reflection->getValue($this->controller));
    }

    // =========================================================================
    // Class structure tests
    // =========================================================================

    #[Test]
    public function classExtendsBaseController(): void
    {
        $reflection = new \ReflectionClass(TextController::class);
        $this->assertSame(
            'Lukaisu\Shared\Http\BaseController',
            $reflection->getParentClass()->getName()
        );
    }

    #[Test]
    public function classHasRequiredPublicMethods(): void
    {
        $reflection = new \ReflectionClass(TextController::class);

        $expectedMethods = [
            'delete', 'archive',
            'unarchive', 'deleteArchived',
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "TextController should have method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPublic(),
                "Method $methodName should be public"
            );
        }
    }

    #[Test]
    public function subControllersHaveExpectedMethods(): void
    {
        // TextCrudController — the texts-list marked-action handler (edit +
        // handleMarkAction / handleTextOperation / showTextsList) was dropped
        // under the headless cut; bulk actions moved to /api/v1/texts/bulk-action.
        $crudMethods = ['delete', 'archive', 'unarchive'];
        $crudReflection = new \ReflectionClass(TextCrudController::class);
        foreach ($crudMethods as $method) {
            $this->assertTrue($crudReflection->hasMethod($method), "TextCrudController should have: $method");
        }

        // ArchivedTextController — archived + handleArchivedMarkAction dropped too.
        $archMethods = ['deleteArchived'];
        $archReflection = new \ReflectionClass(ArchivedTextController::class);
        foreach ($archMethods as $method) {
            $this->assertTrue($archReflection->hasMethod($method), "ArchivedTextController should have: $method");
        }
    }

    // =========================================================================
    // delete() method tests
    // =========================================================================

    #[Test]
    public function deleteCallsServiceAndRedirects(): void
    {
        $this->textService->expects($this->once())
            ->method('deleteText')
            ->with(42);

        $result = $this->controller->delete(42);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function deleteRedirectsToTextsList(): void
    {
        $this->textService->method('deleteText')->willReturn(['sentences' => 0, 'textItems' => 0]);

        $result = $this->controller->delete(1);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('/texts', $result->getUrl());
    }

    // =========================================================================
    // archive() method tests
    // =========================================================================

    #[Test]
    public function archiveCallsServiceAndRedirects(): void
    {
        $this->textService->expects($this->once())
            ->method('archiveText')
            ->with(42);

        $result = $this->controller->archive(42);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function archiveRedirectsToTextsList(): void
    {
        $this->textService->method('archiveText')->willReturn(['sentences' => 0, 'textItems' => 0]);

        $result = $this->controller->archive(1);

        $this->assertSame('/texts', $result->getUrl());
    }

    // =========================================================================
    // unarchive() method tests
    // =========================================================================

    #[Test]
    public function unarchiveCallsServiceAndRedirects(): void
    {
        $this->textService->expects($this->once())
            ->method('unarchiveText')
            ->with(42);

        $result = $this->controller->unarchive(42);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function unarchiveRedirectsToArchivedTextsList(): void
    {
        $this->textService->method('unarchiveText')
            ->willReturn(['success' => true, 'sentences' => 0, 'textItems' => 0]);

        $result = $this->controller->unarchive(1);

        $this->assertSame('/text/archived', $result->getUrl());
    }

    // =========================================================================
    // deleteArchived() method tests
    // =========================================================================

    #[Test]
    public function deleteArchivedCallsServiceAndRedirects(): void
    {
        $this->textService->expects($this->once())
            ->method('deleteArchivedText')
            ->with(42);

        $result = $this->controller->deleteArchived(42);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function deleteArchivedRedirectsToArchivedList(): void
    {
        $this->textService->method('deleteArchivedText')->willReturn([]);

        $result = $this->controller->deleteArchived(1);

        $this->assertSame('/text/archived', $result->getUrl());
    }

    // =========================================================================
    // Method signature tests
    // =========================================================================

    #[Test]
    public function deleteMethodAcceptsInt(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'delete');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
        $this->assertFalse($params[0]->allowsNull());
    }

    #[Test]
    public function archiveMethodAcceptsInt(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'archive');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
    }

    #[Test]
    public function editSingleMethodAcceptsInt(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'editSingle');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
    }

    #[Test]
    public function deleteReturnsRedirectResponse(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'delete');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame(RedirectResponse::class, $returnType->getName());
    }

    #[Test]
    public function archiveReturnsRedirectResponse(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'archive');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame(RedirectResponse::class, $returnType->getName());
    }

    #[Test]
    public function unarchiveReturnsRedirectResponse(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'unarchive');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame(RedirectResponse::class, $returnType->getName());
    }

    #[Test]
    public function editMethodAcceptsArrayParams(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'edit');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('params', $params[0]->getName());
    }

    #[Test]
    public function newMethodAcceptsArrayParams(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'new');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('params', $params[0]->getName());
    }

    #[Test]
    public function archivedEditAcceptsIntParam(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'archivedEdit');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
    }

    #[Test]
    public function deleteArchivedReturnsRedirectResponse(): void
    {
        $method = new \ReflectionMethod(TextController::class, 'deleteArchived');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame(RedirectResponse::class, $returnType->getName());
    }
}
