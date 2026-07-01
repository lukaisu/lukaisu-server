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
            ['/word/bulk-translate'], ['/word/upload'],
            ['/tags'], ['/tags/text'], ['/review'],
            // Tag new/edit forms 302 into the bundled Svelte TagForm island.
            ['/tags/new'], ['/tags/5/edit'], ['/tags/text/new'], ['/tags/text/5/edit'],
            // Feed new/edit forms (Job B, D3d) 302 into the bundled Svelte
            // FeedFormPage island.
            ['/feeds/new'], ['/feeds/5/edit'],
            ['/profile/preferences'],
            // Login is now cut over: GET /login 302s to the bundled LoginPage
            // island (token-API login), replacing the server-rendered form.
            ['/login'],
            // Register + the password flows are now cut over: their GET routes 302
            // to the bundled token-API Svelte islands (register / forgot / reset /
            // recover), replacing the server-rendered forms.
            ['/register'], ['/password/forgot'], ['/password/reset'], ['/password/recover'],
            // Dictionary import GET (Job B, D3c) → bundled Svelte island.
            ['/dictionaries/import'], ['/languages/5/dictionaries/import'],
            // Book list + detail (Phase R) → bundled Svelte BooksListPage /
            // BookDetailPage islands; they read + delete via /api/v1/books.
            ['/books'], ['/book/5'],
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
            'word show (not bundled)' => ['/word/5', 'TermDisplayController@showWord'],
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
            // Tag-form island: create + single-tag load (edit prefill).
            'create term tag' => ['POST', 'tags/term'],
            'create text tag' => ['POST', 'tags/text'],
            'load single term tag' => ['GET', 'tags/term/5'],
            'load single text tag' => ['GET', 'tags/text/5'],
            // Statistics chart data moved off /profile/statistics/config (Phase R).
            'statistics chart data' => ['GET', 'activity/statistics'],
            // Starter-vocab config + import/enrich moved off their cookie routes
            // (Phase R); LanguageApiHandler dispatches all three to
            // StarterVocabController. Shared by the StarterVocab + WordUpload islands.
            'starter-vocab config' => ['GET', 'languages/5/starter-vocab/config'],
            'starter-vocab import' => ['POST', 'languages/5/starter-vocab/import'],
            'starter-vocab enrich' => ['POST', 'languages/5/starter-vocab/enrich'],
            // Word-upload bootstrap config + multipart file POST moved off their
            // cookie routes (Phase R); VocabularyApiRouter dispatches both to
            // TermImportController.
            'word-upload config' => ['GET', 'terms/upload/config'],
            'word-upload file' => ['POST', 'terms/upload'],
            // Feed-form bootstrap config moved off its cookie routes (Phase R);
            // FeedApiHandler dispatches both to FeedController.
            'feed new config' => ['GET', 'feeds/new/config'],
            'feed edit config' => ['GET', 'feeds/5/edit/config'],
            // Dictionary-file multipart import moved off its cookie routes (Phase R);
            // DictionaryApiHandler dispatches it to DictionaryController@processImport.
            'dict-import file' => ['POST', 'local-dictionaries/import'],
            // Bulk-translate bootstrap config + save moved off their cookie routes
            // (Phase R); VocabularyApiRouter dispatches both to TermImportController
            // (@config and @bulkTranslate).
            'bulk-translate config' => ['GET', 'terms/bulk-translate/config'],
            'bulk-translate save' => ['POST', 'terms/bulk-translate'],
            // Terms export moved off the native POST /words form (Phase R);
            // VocabularyApiRouter dispatches it to WordListApiHandler@exportMarkedTerms.
            'terms export' => ['POST', 'terms/export'],
            // Books moved into the bundle (Phase R): the list/detail pages read
            // /api/v1/books and delete/progress hit books/{id}. All resolve via
            // the first-segment 'books' fallback, so 'books' must allow every
            // method BookApiHandler serves (GET/PUT/DELETE).
            'books list' => ['GET', 'books'],
            'book detail' => ['GET', 'books/5'],
            'book delete' => ['DELETE', 'books/5'],
            'book progress' => ['PUT', 'books/5/progress'],
            // POST books registers a book over on-device-imported chapter texts
            // (the EPUB import bridge) → BookApiHandler@createBook.
            'book create' => ['POST', 'books'],
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
