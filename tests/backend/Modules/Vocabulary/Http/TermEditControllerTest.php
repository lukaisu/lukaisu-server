<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\Http;

use Lukaisu\Modules\Vocabulary\Http\TermEditController;
use Lukaisu\Modules\Vocabulary\Http\VocabularyBaseController;
use Lukaisu\Modules\Vocabulary\Application\VocabularyFacade;
use Lukaisu\Modules\Vocabulary\Domain\Term;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TermEditController.
 *
 * The server-rendered create/edit forms were retired under the headless cut;
 * this controller now only serves inline edit (AJAX) and term deletion.
 */
class TermEditControllerTest extends TestCase
{
    /** @var VocabularyFacade&MockObject */
    private VocabularyFacade $facade;

    private TermEditController $controller;

    protected function setUp(): void
    {
        $this->facade = $this->createMock(VocabularyFacade::class);
        $this->controller = new TermEditController($this->facade);
    }

    // =========================================================================
    // Constructor tests
    // =========================================================================

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(TermEditController::class, $this->controller);
    }

    #[Test]
    public function constructorAcceptsNullParameter(): void
    {
        $controller = new TermEditController(null);
        $this->assertInstanceOf(TermEditController::class, $controller);
    }

    #[Test]
    public function constructorSetsFacadeProperty(): void
    {
        $reflection = new \ReflectionProperty(TermEditController::class, 'facade');

        $this->assertSame($this->facade, $reflection->getValue($this->controller));
    }

    // =========================================================================
    // Class structure tests
    // =========================================================================

    #[Test]
    public function classExtendsVocabularyBaseController(): void
    {
        $reflection = new \ReflectionClass(TermEditController::class);

        $this->assertSame(
            VocabularyBaseController::class,
            $reflection->getParentClass()->getName()
        );
    }

    #[Test]
    public function classHasRequiredPublicMethods(): void
    {
        $reflection = new \ReflectionClass(TermEditController::class);

        $expectedMethods = [
            'inlineEdit',
            'deleteWord',
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "TermEditController should have method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPublic(),
                "Method $methodName should be public"
            );
        }
    }

    // =========================================================================
    // Method signature tests
    // =========================================================================

    #[Test]
    public function inlineEditAcceptsArrayParameter(): void
    {
        $method = new \ReflectionMethod(TermEditController::class, 'inlineEdit');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('params', $params[0]->getName());
    }

    #[Test]
    public function deleteWordAcceptsIntParameter(): void
    {
        $method = new \ReflectionMethod(TermEditController::class, 'deleteWord');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
        $this->assertSame('int', $params[0]->getType()->getName());
    }

    #[Test]
    public function deleteWordReturnsRedirectResponse(): void
    {
        $method = new \ReflectionMethod(TermEditController::class, 'deleteWord');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame(RedirectResponse::class, $returnType->getName());
    }

    // =========================================================================
    // deleteWord tests
    // =========================================================================

    #[Test]
    public function deleteWordCallsFacadeWithCorrectId(): void
    {
        $this->facade->expects($this->once())
            ->method('deleteTerm')
            ->with(42)
            ->willReturn(true);

        $result = $this->controller->deleteWord(42);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function deleteWordReturnsRedirectToWordsList(): void
    {
        $this->facade->expects($this->once())
            ->method('deleteTerm')
            ->willReturn(true);

        $result = $this->controller->deleteWord(1);

        // Verify it's a RedirectResponse pointing to /words
        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function deleteWordWithNonexistentId(): void
    {
        $this->facade->expects($this->once())
            ->method('deleteTerm')
            ->with(9999)
            ->willReturn(false);

        $result = $this->controller->deleteWord(9999);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    // =========================================================================
    // inlineEdit tests
    // =========================================================================

    #[Test]
    public function inlineEditWithTranslationPrefix(): void
    {
        // Set up POST data for translation edit
        $_POST['id'] = 'trans123';
        $_POST['value'] = 'hello';

        $mockTerm = $this->createMock(Term::class);
        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(123)
            ->willReturn($mockTerm);

        $this->facade->expects($this->once())
            ->method('updateTerm')
            ->with(123, null, 'hello', null, null, null);

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertSame('hello', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditWithEmptyTranslationSetsAsterisk(): void
    {
        $_POST['id'] = 'trans456';
        $_POST['value'] = '';

        $mockTerm = $this->createMock(Term::class);
        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(456)
            ->willReturn($mockTerm);

        $this->facade->expects($this->once())
            ->method('updateTerm')
            ->with(456, null, '*', null, null, null);

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertSame('*', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditWithRomanizationPrefix(): void
    {
        $_POST['id'] = 'roman789';
        $_POST['value'] = 'romaji';

        $mockTerm = $this->createMock(Term::class);
        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(789)
            ->willReturn($mockTerm);

        $this->facade->expects($this->once())
            ->method('updateTerm')
            ->with(789, null, null, null, null, 'romaji');

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertSame('romaji', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditTranslationNotFoundReturnsError(): void
    {
        $_POST['id'] = 'trans999';
        $_POST['value'] = 'test';

        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(999)
            ->willReturn(null);

        $this->facade->expects($this->never())
            ->method('updateTerm');

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertSame('ERROR - term not found!', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditRomanizationNotFoundReturnsError(): void
    {
        $_POST['id'] = 'roman999';
        $_POST['value'] = 'test';

        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(999)
            ->willReturn(null);

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertSame('ERROR - term not found!', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditWithUnknownPrefixReturnsRefreshError(): void
    {
        $_POST['id'] = 'unknown123';
        $_POST['value'] = 'test';

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertSame('ERROR - please refresh page!', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditWithEmptyIdReturnsRefreshError(): void
    {
        $_POST['id'] = '';
        $_POST['value'] = 'test';

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertSame('ERROR - please refresh page!', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditEscapesHtmlInTranslation(): void
    {
        $_POST['id'] = 'trans10';
        $_POST['value'] = '<script>alert("xss")</script>';

        $mockTerm = $this->createMock(Term::class);
        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(10)
            ->willReturn($mockTerm);

        $this->facade->expects($this->once())
            ->method('updateTerm');

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);

        unset($_POST['id'], $_POST['value']);
    }

    #[Test]
    public function inlineEditEscapesHtmlInRomanization(): void
    {
        $_POST['id'] = 'roman10';
        $_POST['value'] = '<b>bold</b>';

        $mockTerm = $this->createMock(Term::class);
        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(10)
            ->willReturn($mockTerm);

        $this->facade->expects($this->once())
            ->method('updateTerm');

        ob_start();
        $this->controller->inlineEdit([]);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<b>', $output);
        $this->assertStringContainsString('&lt;b&gt;', $output);

        unset($_POST['id'], $_POST['value']);
    }
}
