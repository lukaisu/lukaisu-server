<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Dictionary\Http;

use Lukaisu\Modules\Dictionary\Http\DictionaryController;
use Lukaisu\Modules\Dictionary\Application\DictionaryFacade;
use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for DictionaryController.
 *
 * Tests class structure, method signatures, and pure-logic private methods
 * (getImportOptions) without requiring a database connection.
 */
class DictionaryControllerTest extends TestCase
{
    /** @var DictionaryFacade&MockObject */
    private DictionaryFacade $dictionaryFacade;

    private DictionaryController $controller;

    private array $originalRequest;
    private array $originalServer;
    private array $originalGet;
    private array $originalPost;

    protected function setUp(): void
    {
        $this->dictionaryFacade = $this->createMock(DictionaryFacade::class);
        $this->controller = new DictionaryController($this->dictionaryFacade);

        // Save superglobals
        $this->originalRequest = $_REQUEST;
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
    }

    protected function tearDown(): void
    {
        // Restore superglobals
        $_REQUEST = $this->originalRequest;
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
    }

    // =========================================================================
    // Constructor and class structure tests
    // =========================================================================

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(DictionaryController::class, $this->controller);
    }

    #[Test]
    public function classExtendsBaseController(): void
    {
        $reflection = new ReflectionClass(DictionaryController::class);

        $this->assertSame(
            BaseController::class,
            $reflection->getParentClass()->getName()
        );
    }

    #[Test]
    public function constructorStoresDictionaryFacade(): void
    {
        $prop = new ReflectionProperty(DictionaryController::class, 'dictionaryFacade');

        $this->assertSame($this->dictionaryFacade, $prop->getValue($this->controller));
    }

    // =========================================================================
    // Method signature tests
    // =========================================================================

    #[Test]
    public function classHasRequiredPublicMethods(): void
    {
        $reflection = new ReflectionClass(DictionaryController::class);
        // The server-rendered list + import wizard (index/delete/preview) were
        // dropped under the headless cut; only the app-facing multipart import
        // handler (POST /api/v1/local-dictionaries/import) remains.
        $expectedPublic = ['processImport'];

        foreach ($expectedPublic as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "DictionaryController should have public method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPublic(),
                "Method $methodName should be public"
            );
        }
    }

    #[Test]
    public function classHasRequiredPrivateMethods(): void
    {
        $reflection = new ReflectionClass(DictionaryController::class);
        $expectedPrivate = ['getImportOptions'];

        foreach ($expectedPrivate as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "DictionaryController should have private method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPrivate(),
                "Method $methodName should be private"
            );
        }
    }

    #[Test]
    public function allPublicMethodsAcceptArrayParams(): void
    {
        $reflection = new ReflectionClass(DictionaryController::class);
        $publicMethods = ['processImport'];

        foreach ($publicMethods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $params = $method->getParameters();

            $this->assertCount(
                1,
                $params,
                "Method $methodName should accept exactly one parameter"
            );
            $this->assertSame(
                'params',
                $params[0]->getName(),
                "Method $methodName parameter should be named 'params'"
            );
            $this->assertSame(
                'array',
                $params[0]->getType()->getName(),
                "Method $methodName parameter should be typed as array"
            );
        }
    }

    #[Test]
    public function processImportReturnsJsonResponse(): void
    {
        $method = new ReflectionMethod(DictionaryController::class, 'processImport');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame(JsonResponse::class, $returnType->getName());
    }

    #[Test]
    public function getImportOptionsAcceptsStringParameter(): void
    {
        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('format', $params[0]->getName());
        $this->assertSame('string', $params[0]->getType()->getName());
    }

    // =========================================================================
    // getImportOptions tests (pure logic, no DB)
    // =========================================================================

    #[Test]
    public function getImportOptionsReturnsEmptyForUnknownFormat(): void
    {
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'stardict');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function getImportOptionsCsvDefaultValues(): void
    {
        // No form parameters set - should use defaults
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'csv');

        $this->assertArrayHasKey('delimiter', $result);
        $this->assertSame(',', $result['delimiter']);

        $this->assertArrayHasKey('hasHeader', $result);
        $this->assertTrue($result['hasHeader']);

        $this->assertArrayHasKey('columnMap', $result);
        $this->assertSame(0, $result['columnMap']['term']);
        $this->assertSame(1, $result['columnMap']['definition']);
        $this->assertNull($result['columnMap']['reading']);
        $this->assertNull($result['columnMap']['pos']);
    }

    #[Test]
    public function getImportOptionsCsvTabDelimiterConversion(): void
    {
        $_REQUEST = ['delimiter' => 'tab'];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'csv');

        $this->assertSame("\t", $result['delimiter']);
    }

    #[Test]
    public function getImportOptionsCsvWithColumnMapping(): void
    {
        $_REQUEST = [
            'delimiter' => ';',
            'has_header' => 'no',
            'term_column' => '2',
            'definition_column' => '3',
            'reading_column' => '4',
            'pos_column' => '5',
        ];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'csv');

        $this->assertSame(';', $result['delimiter']);
        $this->assertFalse($result['hasHeader']);
        $this->assertSame(2, $result['columnMap']['term']);
        $this->assertSame(3, $result['columnMap']['definition']);
        $this->assertSame(4, $result['columnMap']['reading']);
        $this->assertSame(5, $result['columnMap']['pos']);
    }

    #[Test]
    public function getImportOptionsTsvUseSameLogicAsCsv(): void
    {
        $_REQUEST = ['delimiter' => '|', 'has_header' => 'yes'];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'tsv');

        $this->assertArrayHasKey('delimiter', $result);
        $this->assertSame('|', $result['delimiter']);
        $this->assertTrue($result['hasHeader']);
    }

    #[Test]
    public function getImportOptionsJsonWithFieldMapping(): void
    {
        $_REQUEST = [
            'term_field' => 'headword',
            'definition_field' => 'meaning',
            'reading_field' => 'kana',
            'pos_field' => 'partOfSpeech',
        ];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'json');

        $this->assertArrayHasKey('fieldMap', $result);
        $this->assertSame('headword', $result['fieldMap']['term']);
        $this->assertSame('meaning', $result['fieldMap']['definition']);
        $this->assertSame('kana', $result['fieldMap']['reading']);
        $this->assertSame('partOfSpeech', $result['fieldMap']['pos']);
    }

    #[Test]
    public function getImportOptionsJsonWithoutFieldMappingReturnsNoFieldMap(): void
    {
        // Empty field names => no fieldMap key
        $_REQUEST = [
            'term_field' => '',
            'definition_field' => '',
        ];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'json');

        $this->assertArrayNotHasKey('fieldMap', $result);
    }

    #[Test]
    public function getImportOptionsJsonPartialFieldMappingIncludesFieldMap(): void
    {
        // Only term_field set (non-empty) => fieldMap should exist
        $_REQUEST = [
            'term_field' => 'word',
            'definition_field' => '',
            'reading_field' => '',
            'pos_field' => '',
        ];
        $_GET = [];
        $_POST = [];

        $method = new ReflectionMethod(DictionaryController::class, 'getImportOptions');

        $result = $method->invoke($this->controller, 'json');

        $this->assertArrayHasKey('fieldMap', $result);
        $this->assertSame('word', $result['fieldMap']['term']);
        $this->assertNull($result['fieldMap']['definition']);
        $this->assertNull($result['fieldMap']['reading']);
        $this->assertNull($result['fieldMap']['pos']);
    }

    // =========================================================================
    // processImport validation tests
    // =========================================================================

    #[Test]
    public function processImportRejectsInvalidLanguageWithJsonError(): void
    {
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_SERVER = ['REQUEST_METHOD' => 'POST'];

        // langId resolves to 0 (no params['id'], no lang_id field), so the import
        // returns a JSON error and never touches the facade (Phase R: the method
        // now answers with JSON instead of redirecting).
        $this->dictionaryFacade->expects($this->never())->method('create');
        $this->dictionaryFacade->expects($this->never())->method('addEntriesBatch');

        $response = $this->controller->processImport([]);

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    #[Test]
    public function processImportUsesRouteParamIdOverFormField(): void
    {
        // When params['id'] is set, it wins over the lang_id form field: the
        // dictionary is created for language 5, not 99.
        $_REQUEST = ['lang_id' => '99', 'format' => 'csv'];
        $_GET = [];
        $_POST = [];
        $_SERVER = ['REQUEST_METHOD' => 'POST'];
        $_FILES = [];

        $this->dictionaryFacade->expects($this->once())
            ->method('create')
            ->with(5, $this->anything(), 'csv')
            ->willReturn(7);
        // No uploaded file => JSON error before any entries are imported.
        $this->dictionaryFacade->expects($this->never())->method('addEntriesBatch');

        $response = $this->controller->processImport(['id' => '5']);

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    #[Test]
    public function processImportDefaultFormatIsCsv(): void
    {
        // Verify the default format parameter
        $method = new ReflectionMethod(DictionaryController::class, 'processImport');
        $source = file_get_contents(
            (new ReflectionClass(DictionaryController::class))->getFileName()
        );

        // The source should contain param('format', 'csv')
        $this->assertStringContainsString("param('format', 'csv')", $source);
    }
}
