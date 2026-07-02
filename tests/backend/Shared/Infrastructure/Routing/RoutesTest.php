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
        $this->assertEquals('not_found', $result['type']);
    }

    public function testIndexPhpRoute(): void
    {
        $result = $this->simulateRequest('/index.php');
        $this->assertEquals('not_found', $result['type']);
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

    // ==================== WORD ROUTES TESTS ====================
    // The word/term list + create/edit forms have no server route (headless
    // cut, R6f): they are served exclusively by a connected client through
    // /api/v1. Only this one data route remains here.
    public function testWordInlineEditRoute(): void
    {
        $result = $this->simulateRequest('/word/inline-edit');
        $this->assertEquals('handler', $result['type']);
        $this->assertEquals(
            'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@inlineEdit',
            $result['handler']
        );
        $this->assertHandlerFileExists($result['handler']);
    }

    // ==================== ADMIN ROUTES TESTS ====================
    // The admin browser UI was dropped under the headless cut (Option A); admin
    // settings has no server route either (R6f, headless client only) — only
    // this legacy redirect remains.
    public function testAdminStatisticsRedirectsToProfileStatistics(): void
    {
        $result = $this->simulateRequest('/admin/statistics');
        $this->assertEquals('handler', $result['type']);
        $this->assertEquals(
            'Lukaisu\\Modules\\User\\Http\\StatisticsController@redirectFromAdmin',
            $result['handler']
        );
        $this->assertHandlerFileExists($result['handler']);
    }

    // ==================== DROPPED BROWSER ROUTES ====================
    /**
     * R6f (headless cut, Option A): the server no longer serves a browser UI.
     * BundleController + the /app bundle-serving + the Job-A cut-over redirects
     * are gone, so every reading/learning page GET now 404s — a connected
     * client (mobile app or any /api/v1 consumer) is the only way to reach
     * these surfaces. Representative sample across every former category.
     */
    #[DataProvider('droppedBrowserRoutesProvider')]
    public function testDroppedBrowserRoutesReturn404(string $path): void
    {
        $result = $this->simulateRequest($path);
        $this->assertEquals('not_found', $result['type'], "Route {$path} should no longer resolve");
    }

    public static function droppedBrowserRoutesProvider(): array
    {
        return [
            'connect' => ['/connect'],
            'texts list' => ['/texts'],
            'text read' => ['/text/read'],
            'text print-plain' => ['/text/print-plain'],
            'text check' => ['/text/check'],
            'text archived' => ['/text/archived'],
            'words list' => ['/words'],
            'words edit list' => ['/words/edit'],
            'words new' => ['/words/new'],
            'word new' => ['/word/new'],
            'word bulk-translate' => ['/word/bulk-translate'],
            'word upload' => ['/word/upload'],
            'review index' => ['/review'],
            'languages list' => ['/languages'],
            'tags list' => ['/tags'],
            'tags text' => ['/tags/text'],
            'feeds index' => ['/feeds'],
            'feeds manage' => ['/feeds/manage'],
            'admin settings' => ['/admin/settings'],
            'profile statistics' => ['/profile/statistics'],
            'app shell' => ['/app'],
            'app page' => ['/app/read.html'],
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
            'logout'               => ['/logout'],
            'resend verification'  => ['/email/resend-verification'],
            'forgot password'      => ['/password/forgot'],
            'reset password'       => ['/password/reset'],
        ];
    }
}
