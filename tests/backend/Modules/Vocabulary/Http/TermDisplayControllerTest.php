<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\Http;

use Lukaisu\Modules\Vocabulary\Http\TermDisplayController;
use Lukaisu\Modules\Vocabulary\Http\VocabularyBaseController;
use Lukaisu\Modules\Vocabulary\Application\UseCases\FindSimilarTerms;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TermDisplayController.
 *
 * The read-only term detail page was dropped under the headless cut; this
 * controller now only serves the similar-terms lookup (AJAX).
 */
class TermDisplayControllerTest extends TestCase
{
    /** @var FindSimilarTerms&MockObject */
    private FindSimilarTerms $findSimilarTerms;

    private TermDisplayController $controller;

    protected function setUp(): void
    {
        $this->findSimilarTerms = $this->createMock(FindSimilarTerms::class);
        $this->controller = new TermDisplayController($this->findSimilarTerms);
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
    public function constructorAcceptsNullParameter(): void
    {
        $controller = new TermDisplayController(null);
        $this->assertInstanceOf(TermDisplayController::class, $controller);
    }

    #[Test]
    public function constructorSetsFindSimilarTermsProperty(): void
    {
        $reflection = new \ReflectionProperty(TermDisplayController::class, 'findSimilarTerms');

        $this->assertSame($this->findSimilarTerms, $reflection->getValue($this->controller));
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

        $this->assertTrue(
            $reflection->hasMethod('similarTerms'),
            'TermDisplayController should have method: similarTerms'
        );
        $this->assertTrue(
            $reflection->getMethod('similarTerms')->isPublic(),
            'Method similarTerms should be public'
        );
    }

    // =========================================================================
    // Method signature tests
    // =========================================================================

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
