<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Text\Http;

use Lukaisu\Modules\Text\Http\TextController;
use Lukaisu\Modules\Text\Http\TextCrudController;
use Lukaisu\Modules\Text\Http\ArchivedTextController;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Modules\Language\Application\LanguageFacade;
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

    /** @var LanguageFacade&MockObject */
    private LanguageFacade $languageService;

    private TextController $controller;
    private TextCrudController $crudController;
    private ArchivedTextController $archivedController;

    protected function setUp(): void
    {
        if (!defined('LUKAISU_TEST_DB_AVAILABLE') || !LUKAISU_TEST_DB_AVAILABLE) {
            $this->markTestSkipped('Database connection required');
        }
        $this->textService = $this->createMock(TextFacade::class);
        $this->languageService = $this->createMock(LanguageFacade::class);

        $this->controller = new TextController(
            $this->textService,
            $this->languageService
        );

        $this->crudController = new TextCrudController(
            $this->textService,
            $this->languageService
        );

        $this->archivedController = new ArchivedTextController(
            $this->textService,
            $this->languageService
        );
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
        $controller = new TextController(null, null);
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
            'new', 'editSingle', 'delete', 'archive',
            'unarchive', 'edit',
            'archived', 'archivedEdit', 'deleteArchived',
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
        // TextCrudController
        $crudMethods = ['new', 'editSingle', 'delete', 'archive', 'unarchive', 'edit',
            'handleMarkAction', 'handleTextOperation', 'showNewTextForm', 'showEditTextForm', 'showTextsList'];
        $crudReflection = new \ReflectionClass(TextCrudController::class);
        foreach ($crudMethods as $method) {
            $this->assertTrue($crudReflection->hasMethod($method), "TextCrudController should have: $method");
        }

        // ArchivedTextController
        $archMethods = ['archived', 'archivedEdit', 'deleteArchived', 'handleArchivedMarkAction'];
        $archReflection = new \ReflectionClass(ArchivedTextController::class);
        foreach ($archMethods as $method) {
            $this->assertTrue($archReflection->hasMethod($method), "ArchivedTextController should have: $method");
        }
    }

    #[Test]
    public function subControllersHaveViewsConstants(): void
    {
        foreach ([TextCrudController::class, ArchivedTextController::class] as $class) {
            $reflection = new \ReflectionClassConstant($class, 'MODULE_VIEWS');
            $this->assertStringEndsWith('/Views', $reflection->getValue());
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
    // handleMarkAction() tests via TextCrudController
    // =========================================================================

    #[Test]
    public function handleMarkActionReturnsDefaultForEmptyMarked(): void
    {
        $result = $this->crudController->handleMarkAction('del', [], '');

        $this->assertSame('Multiple Actions: 0', $result);
    }

    #[Test]
    public function handleMarkActionDeleteCallsService(): void
    {
        $this->textService->expects($this->once())
            ->method('deleteTexts')
            ->with([1, 2, 3])
            ->willReturn(['count' => 3]);

        $result = $this->crudController->handleMarkAction('del', ['1', '2', '3'], '');

        $this->assertSame('Texts deleted: 3', $result);
    }

    #[Test]
    public function handleMarkActionArchiveCallsService(): void
    {
        $this->textService->expects($this->once())
            ->method('archiveTexts')
            ->with([1, 2])
            ->willReturn(['count' => 2]);

        $result = $this->crudController->handleMarkAction('arch', ['1', '2'], '');

        $this->assertSame('Archived Text(s): 2', $result);
    }

    #[Test]
    public function handleMarkActionSetSentencesCallsService(): void
    {
        $this->textService->expects($this->once())
            ->method('setTermSentences')
            ->with([1], false)
            ->willReturn(5);

        $result = $this->crudController->handleMarkAction('setsent', ['1'], '');

        $this->assertSame('Term sentences set: 5', $result);
    }

    #[Test]
    public function handleMarkActionSetActiveSentencesCallsService(): void
    {
        $this->textService->expects($this->once())
            ->method('setTermSentences')
            ->with([1], true)
            ->willReturn(3);

        $result = $this->crudController->handleMarkAction('setactsent', ['1'], '');

        $this->assertSame('Active term sentences set: 3', $result);
    }

    #[Test]
    public function handleMarkActionRebuildCallsService(): void
    {
        $this->textService->expects($this->once())
            ->method('rebuildTexts')
            ->with([1, 2])
            ->willReturn(2);

        $result = $this->crudController->handleMarkAction('rebuild', ['1', '2'], '');

        $this->assertSame('Rebuilt Text(s): 2', $result);
    }

    #[Test]
    public function handleMarkActionReviewReturnsRedirect(): void
    {
        $result = $this->crudController->handleMarkAction('review', ['1', '2'], '');

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function handleMarkActionReviewRedirectsToReview(): void
    {
        $result = $this->crudController->handleMarkAction('review', ['1'], '');

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('/review?selection=3', $result->getUrl());
    }

    #[Test]
    public function handleMarkActionDeltagReturnsRedirect(): void
    {
        $result = $this->crudController->handleMarkAction('deltag', ['1'], 'tag');

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function handleMarkActionDeltagRedirectsToTexts(): void
    {
        $result = $this->crudController->handleMarkAction('deltag', ['1'], 'tag');

        $this->assertSame('/texts', $result->getUrl());
    }

    #[Test]
    public function handleMarkActionUnknownActionReturnsDefault(): void
    {
        $result = $this->crudController->handleMarkAction('unknownaction', ['1'], '');

        $this->assertSame('Multiple Actions: 0', $result);
    }

    // =========================================================================
    // handleArchivedMarkAction() tests via ArchivedTextController
    // =========================================================================

    #[Test]
    public function handleArchivedMarkActionReturnsDefaultForEmptyMarked(): void
    {
        $result = $this->archivedController->handleArchivedMarkAction('del', [], '');

        $this->assertSame('Multiple Actions: 0', $result);
    }

    #[Test]
    public function handleArchivedMarkActionDeleteCallsService(): void
    {
        $this->textService->expects($this->once())
            ->method('deleteArchivedTexts')
            ->with([1, 2])
            ->willReturn(['count' => 2]);

        $result = $this->archivedController->handleArchivedMarkAction('del', ['1', '2'], '');

        $this->assertSame('Archived Texts deleted: 2', $result);
    }

    #[Test]
    public function handleArchivedMarkActionUnarchiveCallsService(): void
    {
        $this->textService->expects($this->once())
            ->method('unarchiveTexts')
            ->with([1])
            ->willReturn(['count' => 1]);

        $result = $this->archivedController->handleArchivedMarkAction('unarch', ['1'], '');

        $this->assertSame('Unarchived Text(s): 1', $result);
    }

    #[Test]
    public function handleArchivedMarkActionDeltagReturnsRedirect(): void
    {
        $result = $this->archivedController->handleArchivedMarkAction('deltag', ['1'], 'tag');

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function handleArchivedMarkActionDeltagRedirectsToArchived(): void
    {
        $result = $this->archivedController->handleArchivedMarkAction('deltag', ['1'], 'tag');

        $this->assertSame('/text/archived', $result->getUrl());
    }

    #[Test]
    public function handleArchivedMarkActionUnknownReturnsDefault(): void
    {
        $result = $this->archivedController->handleArchivedMarkAction('unknownaction', ['1'], '');

        $this->assertSame('Multiple Actions: 0', $result);
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
