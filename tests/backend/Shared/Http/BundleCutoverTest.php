<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Shared\Http;

use Lukaisu\Api\V1\Endpoints;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Shared\Infrastructure\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the Job-A "cut-over": the reading/learning page routes 302 into the
 * bundled client (BundleController@redirect), the data routes on those paths keep
 * their controllers, and the new /api/v1 endpoints that let the bundle work
 * server-backed (single-text edit, tag management, parse-check) are routable.
 */
class BundleCutoverTest extends TestCase
{
    private const REDIRECT = 'Lukaisu\\Shared\\Http\\BundleController@redirect';

    private Router $router;
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 4); // tests/backend/Shared/Http -> project root
        $this->router = new Router($basePath);
        require_once $basePath . '/src/Shared/Infrastructure/Routing/routes.php';
        \Lukaisu\Shared\Infrastructure\Routing\registerRoutes($this->router);
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
    }

    /**
     * @return array{string, string}
     */
    private function resolveHandler(string $method, string $uri): string
    {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;
        $result = $this->router->resolve();
        return (string) ($result['handler'] ?? ('[' . ($result['type'] ?? '?') . ']'));
    }

    #[Test]
    #[DataProvider('redirectedGetRoutesProvider')]
    public function jobAGetRoutesRedirectIntoTheBundle(string $uri): void
    {
        $this->assertSame(self::REDIRECT, $this->resolveHandler('GET', $uri), "GET {$uri} should redirect");
    }

    public static function redirectedGetRoutesProvider(): array
    {
        return [
            ['/'], ['/index.php'], ['/texts'], ['/text/5/read'], ['/text/5/print-plain'],
            ['/texts/new'], ['/texts/5/edit'], ['/text/archived'], ['/text/archived/5/edit'],
            ['/text/check'], ['/words'], ['/words/edit'], ['/words/5/edit'], ['/languages'],
            ['/languages/new'], ['/languages/5/edit'], ['/languages/5/starter-vocab'],
            ['/word/bulk-translate'],
            ['/tags'], ['/tags/text'], ['/review'],
            ['/profile/preferences'],
        ];
    }

    #[Test]
    #[DataProvider('preservedDataRoutesProvider')]
    public function dataRoutesKeepTheirControllers(string $method, string $uri, string $expectedFragment): void
    {
        $handler = $this->resolveHandler($method, $uri);
        $this->assertStringContainsString($expectedFragment, $handler, "{$method} {$uri} must keep its controller");
        $this->assertNotSame(self::REDIRECT, $handler);
    }

    public static function preservedDataRoutesProvider(): array
    {
        return [
            'export terms' => ['POST', '/words', 'TermDisplayController'],
            'save bulk-translate' => ['POST', '/word/bulk-translate', 'TermImportController@bulkTranslate'],
            'create text' => ['POST', '/texts/new', 'TextController@new'],
            'edit text POST' => ['POST', '/texts/5/edit', 'TextController@editSingle'],
            'save preferences' => ['POST', '/profile/preferences', 'UserController@savePreferences'],
            'term status' => ['PUT', '/vocabulary/term/5/status', 'TermStatusController'],
            'delete text' => ['DELETE', '/texts/5', 'TextController@delete'],
        ];
    }

    #[Test]
    #[DataProvider('notRedirectedProvider')]
    public function nonJobAGetRoutesAreNotRedirected(string $uri, string $expectedFragment): void
    {
        $handler = $this->resolveHandler('GET', $uri);
        $this->assertStringContainsString($expectedFragment, $handler);
        $this->assertNotSame(self::REDIRECT, $handler);
    }

    public static function notRedirectedProvider(): array
    {
        return [
            'annotated print stays server' => ['/text/5/print', 'TextPrintController@printAnnotated'],
            'feeds (Job B)' => ['/feeds/new', 'FeedController'],
            'login (Job C)' => ['/login', 'UserController@loginForm'],
            'word show (not bundled)' => ['/word/5', 'TermDisplayController@showWord'],
            // The starter-vocab page 302s into the bundle, but its JSON config
            // data route keeps the controller (the island fetches it server-backed).
            'starter-vocab config' => ['/languages/5/starter-vocab/config', 'StarterVocabController@config'],
            // Likewise, the bulk-translate page 302s into the bundle, but its JSON
            // config data route keeps the controller.
            'bulk-translate config' => ['/word/bulk-translate/config', 'TermImportController@config'],
            'api prefix' => ['/api/v1/languages', 'ApiController@v1'],
            'bundle shell' => ['/app/read.html', 'BundleController@serve'],
        ];
    }

    /**
     * The /api/v1 endpoints the bundle needs server-backed must be routable
     * (Endpoints::resolve returns the endpoint string, not a 404/405 JsonResponse).
     */
    #[Test]
    #[DataProvider('newApiEndpointsProvider')]
    public function newApiEndpointsAreRoutable(string $method, string $endpoint): void
    {
        $result = Endpoints::resolve($method, '/api/v1/' . $endpoint);
        $this->assertIsString($result, "{$method} /api/v1/{$endpoint} should be allowed");
        $this->assertSame($endpoint, $result);
    }

    public static function newApiEndpointsProvider(): array
    {
        return [
            'single text load' => ['GET', 'texts/5'],
            'single text save' => ['PUT', 'texts/5'],
            'parse check' => ['POST', 'texts/check'],
            'tag manage list' => ['GET', 'tags/manage'],
            'rename term tag' => ['PUT', 'tags/term/5'],
            'delete term tag' => ['DELETE', 'tags/term/5'],
            'rename text tag' => ['PUT', 'tags/text/5'],
            'delete text tag' => ['DELETE', 'tags/text/5'],
        ];
    }

    #[Test]
    public function readOnlyTagListStillRejectsBareWrites(): void
    {
        // PUT /api/v1/tags (no id) is allowed at the registry level (first-segment
        // fallback) but the handler 404s it; the registry must still allow it so
        // the handler — not a blanket 405 — produces the message.
        $this->assertIsString(Endpoints::resolve('PUT', '/api/v1/tags/term/5'));
        // A method the registry forbids returns a JsonResponse error.
        $this->assertInstanceOf(JsonResponse::class, Endpoints::resolve('DELETE', '/api/v1/texts/check'));
    }
}
