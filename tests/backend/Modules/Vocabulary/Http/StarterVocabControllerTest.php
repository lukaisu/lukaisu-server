<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\Http;

use Lukaisu\Modules\Vocabulary\Http\StarterVocabController;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Modules\Vocabulary\Application\Services\FrequencyImportService;
use Lukaisu\Modules\Vocabulary\Application\Services\WiktionaryEnrichmentService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

class StarterVocabControllerTest extends TestCase
{
    /** @var LanguageFacade&MockObject */
    private LanguageFacade $languageFacade;

    /** @var FrequencyImportService&MockObject */
    private FrequencyImportService $frequencyImportService;

    /** @var WiktionaryEnrichmentService&MockObject */
    private WiktionaryEnrichmentService $enrichmentService;

    private StarterVocabController $controller;

    protected function setUp(): void
    {
        $this->languageFacade = $this->createMock(LanguageFacade::class);
        $this->frequencyImportService = $this->createMock(FrequencyImportService::class);
        $this->enrichmentService = $this->createMock(WiktionaryEnrichmentService::class);
        $this->controller = new StarterVocabController(
            $this->languageFacade,
            $this->frequencyImportService,
            $this->enrichmentService
        );
    }

    // =========================================================================
    // Constructor
    // =========================================================================

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(StarterVocabController::class, $this->controller);
    }

    #[Test]
    public function constructorStoresLanguageFacade(): void
    {
        $ref = new \ReflectionProperty(StarterVocabController::class, 'languageFacade');
        $this->assertSame($this->languageFacade, $ref->getValue($this->controller));
    }

    #[Test]
    public function constructorStoresFrequencyImportService(): void
    {
        $ref = new \ReflectionProperty(StarterVocabController::class, 'frequencyImportService');
        $this->assertSame($this->frequencyImportService, $ref->getValue($this->controller));
    }

    #[Test]
    public function constructorStoresEnrichmentService(): void
    {
        $ref = new \ReflectionProperty(StarterVocabController::class, 'enrichmentService');
        $this->assertSame($this->enrichmentService, $ref->getValue($this->controller));
    }

    // =========================================================================
    // import()
    // =========================================================================

    #[Test]
    public function importMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'import'));
    }

    #[Test]
    public function importMethodHasCorrectSignature(): void
    {
        $method = new \ReflectionMethod(StarterVocabController::class, 'import');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
        $this->assertSame('int', $params[0]->getType()?->getName());
    }

    #[Test]
    public function importReturnTypeIsJsonResponse(): void
    {
        $method = new \ReflectionMethod(StarterVocabController::class, 'import');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('Lukaisu\Shared\Infrastructure\Http\JsonResponse', $returnType->getName());
    }

    // =========================================================================
    // enrich()
    // =========================================================================

    #[Test]
    public function enrichMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'enrich'));
    }

    #[Test]
    public function enrichReturnTypeIsJsonResponse(): void
    {
        $method = new \ReflectionMethod(StarterVocabController::class, 'enrich');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('Lukaisu\Shared\Infrastructure\Http\JsonResponse', $returnType->getName());
    }

    #[Test]
    public function enrichMethodAcceptsIntId(): void
    {
        $method = new \ReflectionMethod(StarterVocabController::class, 'enrich');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
        $this->assertSame('int', $params[0]->getType()?->getName());
    }

    // =========================================================================
    // config()
    // =========================================================================

    #[Test]
    public function configMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'config'));
    }

    #[Test]
    public function configMethodAcceptsIntId(): void
    {
        $method = new \ReflectionMethod(StarterVocabController::class, 'config');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
        $this->assertSame('int', $params[0]->getType()?->getName());
    }

    #[Test]
    public function configReturnTypeIsJsonResponse(): void
    {
        $method = new \ReflectionMethod(StarterVocabController::class, 'config');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('Lukaisu\Shared\Infrastructure\Http\JsonResponse', $returnType->getName());
    }

    #[Test]
    public function configReturnsNotFoundForUnknownLanguage(): void
    {
        $this->languageFacade->method('getById')->willReturn(null);
        $response = $this->controller->config(999);
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function showMethodWasRemoved(): void
    {
        // The GET page route now 302s to the bundled Svelte island; the PHP
        // page-render method was retired in favour of config().
        $this->assertFalse(method_exists($this->controller, 'show'));
    }

    // =========================================================================
    // skip()
    // =========================================================================

    #[Test]
    public function skipMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'skip'));
    }

    // =========================================================================
    // Constants
    // =========================================================================

    #[Test]
    public function allowedCountsContainsExpectedValues(): void
    {
        $ref = new \ReflectionClassConstant(StarterVocabController::class, 'ALLOWED_COUNTS');
        $this->assertSame([50, 100, 500], $ref->getValue());
    }

    #[Test]
    public function allowedModesContainsExpectedValues(): void
    {
        $ref = new \ReflectionClassConstant(StarterVocabController::class, 'ALLOWED_MODES');
        $this->assertSame(['translation', 'definition'], $ref->getValue());
    }
}
