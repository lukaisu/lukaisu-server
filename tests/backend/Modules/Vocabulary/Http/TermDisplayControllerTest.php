<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\Http;

use Lukaisu\Modules\Vocabulary\Http\TermDisplayController;
use Lukaisu\Modules\Vocabulary\Http\VocabularyBaseController;
use Lukaisu\Modules\Vocabulary\Application\VocabularyFacade;
use Lukaisu\Modules\Vocabulary\Application\UseCases\FindSimilarTerms;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TermDisplayController.
 *
 * Tests constructor behavior, class structure, method signatures,
 * show-word/similar-terms logic, and edge cases.
 */
class TermDisplayControllerTest extends TestCase
{
    /** @var VocabularyFacade&MockObject */
    private VocabularyFacade $facade;

    /** @var FindSimilarTerms&MockObject */
    private FindSimilarTerms $findSimilarTerms;

    /** @var LanguageFacade&MockObject */
    private LanguageFacade $languageFacade;

    private TermDisplayController $controller;

    protected function setUp(): void
    {
        $this->facade = $this->createMock(VocabularyFacade::class);
        $this->findSimilarTerms = $this->createMock(FindSimilarTerms::class);
        $this->languageFacade = $this->createMock(LanguageFacade::class);
        $this->controller = new TermDisplayController(
            $this->facade,
            $this->findSimilarTerms,
            $this->languageFacade
        );
    }

    // =========================================================================
    // Constructor tests
    // =========================================================================

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(TermDisplayController::class, $this->controller);
    }

    #[Test]
    public function constructorAcceptsAllNullParameters(): void
    {
        $controller = new TermDisplayController(null, null, null);
        $this->assertInstanceOf(TermDisplayController::class, $controller);
    }

    #[Test]
    public function constructorSetsFacadeProperty(): void
    {
        $reflection = new \ReflectionProperty(TermDisplayController::class, 'facade');

        $this->assertSame($this->facade, $reflection->getValue($this->controller));
    }

    #[Test]
    public function constructorSetsFindSimilarTermsProperty(): void
    {
        $reflection = new \ReflectionProperty(TermDisplayController::class, 'findSimilarTerms');

        $this->assertSame($this->findSimilarTerms, $reflection->getValue($this->controller));
    }

    #[Test]
    public function constructorSetsLanguageFacadeProperty(): void
    {
        $reflection = new \ReflectionProperty(TermDisplayController::class, 'languageFacade');

        $this->assertSame($this->languageFacade, $reflection->getValue($this->controller));
    }

    // =========================================================================
    // Class structure tests
    // =========================================================================

    #[Test]
    public function classExtendsVocabularyBaseController(): void
    {
        $reflection = new \ReflectionClass(TermDisplayController::class);

        $this->assertSame(
            VocabularyBaseController::class,
            $reflection->getParentClass()->getName()
        );
    }

    #[Test]
    public function classHasRequiredPublicMethods(): void
    {
        $reflection = new \ReflectionClass(TermDisplayController::class);

        $expectedMethods = [
            'showWord',
            'similarTerms',
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "TermDisplayController should have method: $methodName"
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
    public function showWordAcceptsNullableIntParameter(): void
    {
        $method = new \ReflectionMethod(TermDisplayController::class, 'showWord');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('wid', $params[0]->getName());
        $this->assertTrue($params[0]->allowsNull());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertNull($params[0]->getDefaultValue());
    }

    #[Test]
    public function similarTermsAcceptsArrayParameter(): void
    {
        $method = new \ReflectionMethod(TermDisplayController::class, 'similarTerms');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('params', $params[0]->getName());
    }

    // =========================================================================
    // similarTerms tests
    // =========================================================================

    #[Test]
    public function similarTermsCallsFindSimilarTerms(): void
    {
        $_REQUEST['lgid'] = '5';
        $_REQUEST['term'] = 'hello';

        $this->findSimilarTerms->expects($this->once())
            ->method('getFormattedTerms')
            ->with(5, 'hello')
            ->willReturn('<tr><td>hello</td></tr>');

        ob_start();
        $this->controller->similarTerms([]);
        $output = ob_get_clean();

        $this->assertSame('<tr><td>hello</td></tr>', $output);

        unset($_REQUEST['lgid'], $_REQUEST['term']);
    }

    #[Test]
    public function similarTermsWithEmptyTermReturnsEmpty(): void
    {
        $_REQUEST['lgid'] = '1';
        $_REQUEST['term'] = '';

        $this->findSimilarTerms->expects($this->once())
            ->method('getFormattedTerms')
            ->with(1, '')
            ->willReturn('');

        ob_start();
        $this->controller->similarTerms([]);
        $output = ob_get_clean();

        $this->assertSame('', $output);

        unset($_REQUEST['lgid'], $_REQUEST['term']);
    }

    #[Test]
    public function similarTermsWithZeroLangId(): void
    {
        // No lgid param, defaults to 0
        $this->findSimilarTerms->expects($this->once())
            ->method('getFormattedTerms')
            ->with(0, '');

        ob_start();
        $this->controller->similarTerms([]);
        ob_get_clean();
    }

    // =========================================================================
    // showWord tests
    // =========================================================================

    #[Test]
    public function showWordWithNullWidAndNoQueryParamOutputsError(): void
    {
        ob_start();
        $this->controller->showWord(null);
        $output = ob_get_clean();

        $this->assertStringContainsString('Word ID is required', $output);
    }

    #[Test]
    public function showWordWithNonexistentTermOutputsNotFound(): void
    {
        $this->facade->expects($this->once())
            ->method('getTerm')
            ->with(42)
            ->willReturn(null);

        ob_start();
        $this->controller->showWord(42);
        $output = ob_get_clean();

        $this->assertStringContainsString('Word not found', $output);
    }

    // =========================================================================
    // View path and render tests
    // =========================================================================

    #[Test]
    public function viewPathPointsToModuleViews(): void
    {
        $reflection = new \ReflectionProperty(VocabularyBaseController::class, 'viewPath');

        $path = $reflection->getValue($this->controller);

        $this->assertStringEndsWith('Views/', $path);
        $this->assertStringContainsString('Vocabulary', $path);
    }

    #[Test]
    public function setViewPathWorks(): void
    {
        $this->controller->setViewPath('/tmp/views');

        $reflection = new \ReflectionProperty(VocabularyBaseController::class, 'viewPath');

        $this->assertSame('/tmp/views/', $reflection->getValue($this->controller));
    }
}
