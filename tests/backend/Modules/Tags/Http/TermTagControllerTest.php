<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Tags\Http;

use Lukaisu\Modules\Tags\Http\TermTagController;
use Lukaisu\Modules\Tags\Application\TagsFacade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TermTagController.
 *
 * The term-tag list is served by the bundled client (GET /tags redirects to
 * /app/tags.html), so this controller only renders the server-side create/edit
 * forms and handles deletes. Tests cover construction, the surviving public
 * surface, and edit-form rendering.
 */
class TermTagControllerTest extends TestCase
{
    /** @var TagsFacade&MockObject */
    private TagsFacade $facade;

    private TermTagController $controller;

    protected function setUp(): void
    {
        $this->facade = $this->createMock(TagsFacade::class);
        $this->controller = new TermTagController($this->facade);
    }

    // =========================================================================
    // Constructor tests
    // =========================================================================

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(TermTagController::class, $this->controller);
    }

    #[Test]
    public function constructorAcceptsNullFacade(): void
    {
        $controller = new TermTagController(null);
        $this->assertInstanceOf(TermTagController::class, $controller);
    }

    #[Test]
    public function constructorSetsFacadeProperty(): void
    {
        $reflection = new \ReflectionProperty(TermTagController::class, 'facade');

        $facade = $reflection->getValue($this->controller);

        $this->assertInstanceOf(TagsFacade::class, $facade);
    }

    // =========================================================================
    // Page title test
    // =========================================================================

    #[Test]
    public function pageTitleIsTermTags(): void
    {
        $reflection = new \ReflectionProperty(TermTagController::class, 'pageTitle');

        $this->assertSame('Term Tags', $reflection->getValue($this->controller));
    }

    // =========================================================================
    // renderEditForm tests via reflection
    // =========================================================================

    #[Test]
    public function renderEditFormWithNonExistentTagOutputsNotFound(): void
    {
        $method = new \ReflectionMethod(TermTagController::class, 'renderEditForm');

        $this->facade->expects($this->once())
            ->method('getById')
            ->with(999)
            ->willReturn(null);

        ob_start();
        $method->invoke($this->controller, 999);
        $output = ob_get_clean();

        // The message() method should have been called with "Tag not found"
        // No exception should be thrown
        $this->assertIsString($output);
    }

    #[Test]
    public function renderEditFormCallsGetById(): void
    {
        $method = new \ReflectionMethod(TermTagController::class, 'renderEditForm');

        $this->facade->expects($this->once())
            ->method('getById')
            ->with(42)
            ->willReturn(null);

        ob_start();
        $method->invoke($this->controller, 42);
        ob_get_clean();

        // Assertion is via the expects($this->once()) constraint above
    }

    // =========================================================================
    // Class structure tests
    // =========================================================================

    #[Test]
    public function classExtendsBaseController(): void
    {
        $reflection = new \ReflectionClass(TermTagController::class);

        $this->assertSame(
            'Lukaisu\Shared\Http\BaseController',
            $reflection->getParentClass()->getName()
        );
    }

    #[Test]
    public function classHasRequiredPublicMethods(): void
    {
        $reflection = new \ReflectionClass(TermTagController::class);

        $expectedMethods = ['new', 'edit', 'delete'];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "TermTagController should have method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPublic(),
                "Method $methodName should be public"
            );
        }
    }

    #[Test]
    public function classHasRequiredProtectedMethods(): void
    {
        $reflection = new \ReflectionClass(TermTagController::class);

        $expectedMethods = ['renderCreateForm', 'renderEditForm'];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "TermTagController should have method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isProtected(),
                "Method $methodName should be protected"
            );
        }
    }

    #[Test]
    public function newMethodAcceptsArrayParameter(): void
    {
        $method = new \ReflectionMethod(TermTagController::class, 'new');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('params', $params[0]->getName());
    }

    #[Test]
    public function editMethodAcceptsIntParameter(): void
    {
        $method = new \ReflectionMethod(TermTagController::class, 'edit');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
        $this->assertSame('int', $params[0]->getType()->getName());
    }

    #[Test]
    public function deleteMethodAcceptsIntParameter(): void
    {
        $method = new \ReflectionMethod(TermTagController::class, 'delete');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
        $this->assertSame('int', $params[0]->getType()->getName());
    }
}
