<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Routing;

use Lukaisu\Shared\Infrastructure\Routing\Router;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Integration tests for all route definitions
 *
 * Tests that all routes defined in routes.php:
 * 1. Resolve correctly to their handlers
 * 2. Have existing handler files (for file handlers) or valid controller format
 */
class RoutesTest extends TestCase
{
    private Router $router;
    private string $basePath;
    private array $originalServer;
    private array $originalGet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = dirname(__DIR__, 5); // Go up to project root
        $this->router = new Router($this->basePath);

        // Load routes
        require_once $this->basePath . '/src/Shared/Infrastructure/Routing/routes.php';
        \Lukaisu\Shared\Infrastructure\Routing\registerRoutes($this->router);

        // Save original superglobals
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        parent::tearDown();
    }

    /**
     * Helper to simulate a request
     */
    private function simulateRequest(
        string $uri,
        string $method = 'GET',
        string $queryString = ''
    ): array {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['QUERY_STRING'] = $queryString;
        $_GET = [];
        if ($queryString) {
            parse_str($queryString, $_GET);
        }

        return $this->router->resolve();
    }

    /**
     * Helper to check if a handler file exists
     */
    private function assertHandlerFileExists(string $handler): void
    {
        // Skip controller format (e.g., UserController@index)
        if (str_contains($handler, '@')) {
            return;
        }

        $fullPath = $this->basePath . '/' . $handler;
        $this->assertFileExists(
            $fullPath,
            "Handler file does not exist: {$handler}"
        );
    }

    // ==================== HOME PAGE TESTS ====================

    public function testHomePageRoute(): void
    {
        $result = $this->simulateRequest('/');
        $this->assertEquals('handler', $result['type']);
        $this->assertEquals('Lukaisu\\Shared\\Http\\BundleController@redirect', $result['handler']);
    }

    public function testIndexPhpRoute(): void
    {
        $result = $this->simulateRequest('/index.php');
        $this->assertEquals('handler', $result['type']);
        $this->assertEquals('Lukaisu\\Shared\\Http\\BundleController@redirect', $result['handler']);
    }
    #[DataProvider('indexPhpWithPathInfoProvider')]
    public function testIndexPhpWithPathInfoRedirect(string $path, string $expectedRedirect): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('redirect', $result['type'], "Route {$path} should be a redirect");
        $this->assertEquals($expectedRedirect, $result['url']);
        $this->assertEquals(301, $result['code']);
    }

    public static function indexPhpWithPathInfoProvider(): array
    {
        return [
            ['/index.php/admin/install-demo', '/admin/install-demo'],
            ['/index.php/feeds', '/feeds'],
            ['/index.php/feeds/manage', '/feeds/manage'],
            ['/index.php/admin/statistics', '/admin/statistics'],
            ['/index.php/text/read', '/text/read'],
        ];
    }

    // ==================== TEXT ROUTES TESTS ====================
    #[DataProvider('textRoutesProvider')]
    public function testTextRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function textRoutesProvider(): array
    {
        $textController = 'Lukaisu\\Modules\\Text\\Http\\TextController';
        // Job-A page GET routes now 302 into the bundled client (the cut-over).
        $redirect = 'Lukaisu\\Shared\\Http\\BundleController@redirect';
        return [
            'text read' => ['/text/read', $redirect],
            'text edit' => ['/text/edit', "{$textController}@edit"],
            'texts list' => ['/texts', $redirect],
            'text display' => ['/text/display', "{$textController}@display"],
            'text print-plain' => ['/text/print-plain', $redirect],
            'text check' => ['/text/check', $redirect],
            'text archived' => ['/text/archived', $redirect],
        ];
    }

    // ==================== WORD ROUTES TESTS ====================
    #[DataProvider('wordRoutesProvider')]
    public function testWordRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function wordRoutesProvider(): array
    {
        $termEditController = 'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController';
        $termDisplayController = 'Lukaisu\\Modules\\Vocabulary\\Http\\TermDisplayController';
        $termStatusController = 'Lukaisu\\Modules\\Vocabulary\\Http\\TermStatusController';
        $termImportController = 'Lukaisu\\Modules\\Vocabulary\\Http\\TermImportController';
        return [
            'word edit' => ['/word/edit', "{$termEditController}@editWord"],
            'word edit-term' => ['/word/edit-term', "{$termEditController}@editTerm"],
            'words edit list' => ['/words/edit', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
            'words list' => ['/words', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
            'word new' => ['/word/new', "{$termEditController}@createWord"],
            'word show' => ['/word/show', "{$termDisplayController}@showWord"],
            'word inline-edit' => ['/word/inline-edit', "{$termEditController}@inlineEdit"],
            // GET 302s into the bundled Svelte BulkTranslate island. Its bootstrap
            // config + save POST moved to /api/v1/terms/bulk-translate{,/config}
            // (Phase R), so only the GET bundle redirect remains here.
            'word bulk-translate' => ['/word/bulk-translate', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
            'word set-all-status' => ['/word/set-all-status', "{$termStatusController}@markAllWords"],
            // GET 302s into the bundled Svelte WordUpload island. Its bootstrap
            // config + file-upload POST moved to /api/v1/terms/upload{,/config}
            // (Phase R), so only the GET bundle redirect remains here.
            'word upload' => ['/word/upload', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
        ];
    }

    // ==================== TEST ROUTES TESTS ====================
    #[DataProvider('reviewTestRoutesProvider')]
    public function testReviewTestRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function reviewTestRoutesProvider(): array
    {
        return [
            'review index' => ['/review', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
        ];
    }

    // ==================== LANGUAGE ROUTES TESTS ====================
    #[DataProvider('languageRoutesProvider')]
    public function testLanguageRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function languageRoutesProvider(): array
    {
        return [
            'languages list' => ['/languages', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
        ];
    }

    // ==================== TAG ROUTES TESTS ====================
    #[DataProvider('tagRoutesProvider')]
    public function testTagRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function tagRoutesProvider(): array
    {
        return [
            'tags list' => ['/tags', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
            'tags text' => ['/tags/text', 'Lukaisu\\Shared\\Http\\BundleController@redirect'],
        ];
    }

    // ==================== FEED ROUTES TESTS ====================
    #[DataProvider('feedRoutesProvider')]
    public function testFeedRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function feedRoutesProvider(): array
    {
        // GET /feeds and /feeds/manage now 302 into the bundled client (Svelte
        // FeedsPage). The legacy Alpine wizard/browse/index/edit + feed-load
        // progress + multi-load routes were deleted; only the create/edit form
        // POST coexistence + JSON config data routes + delete remain (covered by
        // BundleCutoverTest).
        $redirect = 'Lukaisu\\Shared\\Http\\BundleController@redirect';
        return [
            'feeds index' => ['/feeds', $redirect],
            'feeds manage' => ['/feeds/manage', $redirect],
        ];
    }

    /**
     * GET /connect (the packaged-client "choose server + log in" flow) now 302s
     * into the bundled client (Svelte ConnectPage / index.html); the old Alpine
     * clientAuthForm handler + client_auth.php view were retired.
     */
    public function testConnectRouteRedirectsToBundle(): void
    {
        $result = $this->simulateRequest('/connect');
        $this->assertEquals('handler', $result['type'], 'Route /connect should resolve to handler');
        $this->assertEquals('Lukaisu\\Shared\\Http\\BundleController@redirect', $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    // ==================== ADMIN ROUTES TESTS ====================
    #[DataProvider('adminRoutesProvider')]
    public function testAdminRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function adminRoutesProvider(): array
    {
        return [
            'admin backup' => ['/admin/backup', 'Lukaisu\\Modules\\Admin\\Http\\AdminController@backup'],
            'admin wizard' => ['/admin/wizard', 'Lukaisu\\Modules\\Admin\\Http\\AdminController@wizard'],
            'admin statistics (redirect)' => [
                '/admin/statistics',
                'Lukaisu\\Modules\\User\\Http\\StatisticsController@redirectFromAdmin'
            ],
            'admin install-demo' => ['/admin/install-demo', 'Lukaisu\\Modules\\Admin\\Http\\AdminController@installDemo'],
            'admin settings' => ['/admin/settings', 'Lukaisu\\Modules\\Admin\\Http\\AdminController@settings'],
            'admin server-data' => ['/admin/server-data', 'Lukaisu\\Modules\\Admin\\Http\\AdminController@serverData'],
            // GET /profile/statistics 302s into the bundled Svelte StatisticsPage
            // island; its chart data moved to GET /api/v1/activity/statistics
            // (see BundleCutoverTest::newApiEndpointsProvider) under the headless cut.
            'profile statistics' => [
                '/profile/statistics',
                'Lukaisu\\Shared\\Http\\BundleController@redirect'
            ],
        ];
    }

    // ==================== WORDPRESS ROUTES TESTS ====================
    #[DataProvider('wordpressRoutesProvider')]
    public function testWordpressRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function wordpressRoutesProvider(): array
    {
        return [
            'wordpress start' => ['/wordpress/start', 'Lukaisu\\Modules\\User\\Http\\WordPressController@start'],
            'wordpress stop' => ['/wordpress/stop', 'Lukaisu\\Modules\\User\\Http\\WordPressController@stop'],
        ];
    }

    // ==================== API ROUTES TESTS ====================
    #[DataProvider('apiRoutesProvider')]
    public function testApiRoutes(string $path, string $expectedHandler): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('handler', $result['type'], "Route {$path} should resolve to handler");
        $this->assertEquals($expectedHandler, $result['handler']);
        $this->assertHandlerFileExists($result['handler']);
    }

    public static function apiRoutesProvider(): array
    {
        return [
            'api v1' => ['/api/v1', 'ApiController@v1'],
            'api translate' => ['/api/translate', 'Lukaisu\\Modules\\Dictionary\\Http\\TranslationController@translate'],
            'api google' => ['/api/google', 'Lukaisu\\Modules\\Dictionary\\Http\\TranslationController@google'],
            'api glosbe' => ['/api/glosbe', 'Lukaisu\\Modules\\Dictionary\\Http\\TranslationController@glosbe'],
        ];
    }

    // ==================== 404 TESTS ====================

    public function testNonExistentRouteReturns404(): void
    {
        $result = $this->simulateRequest('/nonexistent/route');

        $this->assertEquals('not_found', $result['type']);
        $this->assertEquals('/nonexistent/route', $result['path']);
    }

    public function testUnregisteredPhpFileReturns404(): void
    {
        $result = $this->simulateRequest('/some_unregistered_file.php');

        $this->assertEquals('not_found', $result['type']);
    }

    // ==================== ALL HANDLER FILES EXIST TEST ====================

    public function testAllHandlerFilesExist(): void
    {
        $routesFile = $this->basePath . '/src/Shared/Infrastructure/Routing/routes.php';

        // Extract all handler paths from routes.php
        $content = file_get_contents($routesFile);

        // Match patterns like: $router->register('/path', 'src/php/Legacy/file.php')
        preg_match_all(
            "/register\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]+)['\"]/",
            $content,
            $matches
        );

        $handlers = array_unique($matches[2]);

        // Known missing files (documented issues to fix)
        // If any files are added here, add corresponding test in testKnownRoutingIssues()
        $knownMissingFiles = [];

        $missingFiles = [];

        foreach ($handlers as $handler) {
            // Skip controller format
            if (str_contains($handler, '@')) {
                continue;
            }

            $fullPath = $this->basePath . '/' . $handler;
            if (!file_exists($fullPath)) {
                $missingFiles[] = $handler;
            }
        }

        // Filter out known missing files for the assertion
        $unexpectedMissingFiles = array_diff($missingFiles, array_keys($knownMissingFiles));

        $this->assertEmpty(
            $unexpectedMissingFiles,
            "Unexpected missing handler files: " . implode(', ', $unexpectedMissingFiles)
        );

        // Document known missing files
        // These are tracked in testKnownRoutingIssues() to ensure they get fixed
        $this->assertEquals(
            array_keys($knownMissingFiles),
            $missingFiles,
            "Unexpected missing files found or known issues were fixed"
        );
    }

    /**
     * Test to document and track known routing issues
     *
     * When a route has a known issue (e.g., missing handler file),
     * add it here to track and ensure it gets fixed.
     */
    public function testKnownRoutingIssues(): void
    {
        // No known routing issues - all routes have valid handlers
        $this->assertTrue(true, 'No known routing issues');
    }

    // ==================== ROUTE CONSISTENCY TESTS ====================

    public function testAllRoutesHaveConsistentNaming(): void
    {
        // This test ensures naming conventions are followed
        $routes = [
            // New routes should use hyphens for word separation
            '/word/inline-edit' => 'should use hyphens',
            '/word/bulk-translate' => 'should use hyphens',
            '/admin/install-demo' => 'should use hyphens',
            '/admin/server-data' => 'should use hyphens',
        ];

        foreach ($routes as $route => $message) {
            $result = $this->simulateRequest($route);
            $this->assertEquals(
                'handler',
                $result['type'],
                "Route {$route} should exist ({$message})"
            );
        }
    }

    // ==================== AUTH CSRF COVERAGE ====================

    /**
     * Phase 6.1: every state-changing auth POST must carry CsrfMiddleware.
     *
     * The forms already emit FormHelper::csrfField(); a missing middleware
     * on the matching POST route silently turns the field into theatre and
     * leaves the endpoint open to login-CSRF / forced-registration / forced
     * password-reset-request / verification-email spamming attacks.
     */
    #[DataProvider('authPostRoutesProvider')]
    public function testAuthPostRoutesEnforceCsrf(string $path): void
    {
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $result = $this->router->resolve();

        $this->assertEquals('handler', $result['type'], "{$path} POST should resolve to a handler");
        $middleware = $result['middleware'] ?? [];
        $this->assertContains(
            \Lukaisu\Shared\Infrastructure\Routing\Middleware\CsrfMiddleware::class,
            $middleware,
            "POST {$path} must include CsrfMiddleware (forms emit the token; the route must validate it)"
        );
    }

    public static function authPostRoutesProvider(): array
    {
        return [
            'login'                => ['/login'],
            'register'             => ['/register'],
            'logout'               => ['/logout'],
            'resend verification'  => ['/email/resend-verification'],
            'forgot password'      => ['/password/forgot'],
            'reset password'       => ['/password/reset'],
        ];
    }
}
