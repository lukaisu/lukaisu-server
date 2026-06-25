<?php

/**
 * \file
 * \brief Serves the bundled client (dist-app/) as the server's own web UI.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Shared\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license Unlicense <http://unlicense.org/>
 * @link    https://hugofara.github.io/lukaisu-server/developer/api
 * @since   3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Http;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Http\UrlUtilities;
use Lukaisu\Shared\Infrastructure\Routing\Middleware\CsrfMiddleware;

/**
 * Serves the bundled client (`dist-app/`) as the server's own web UI.
 *
 * The reading/learning surfaces are no longer rendered by PHP views: they ship
 * as static HTML in `dist-app/` (built by `npm run build:app`) and are served
 * here under `/app/`. The PHP server keeps owning `/api/v1`, so the bundle runs
 * in "same-origin server-backed mode": this controller injects a per-session
 * CSRF token, the base path, and a runtime-config blob that the client
 * (`boot.ts`) reads to talk to *this* origin's `/api/v1` with the session cookie
 * instead of going local-first.
 *
 * Only HTML pages flow through here — so the CSRF token stays fresh and the
 * shell is never cached. The bundle's JS/CSS/sound assets are served statically
 * by the Router (`resolveStaticAsset()` maps `/app/*` → `dist-app/*`).
 *
 * @since 3.0.0
 */
final class BundleController
{
    /** Directory holding the built bundle, relative to the application root. */
    private const BUNDLE_DIR = 'dist-app';

    /**
     * Serve a bundled HTML page for a `/app/...` request.
     *
     * The page name is taken from the request path (not from $params), the same
     * way the API prefix handler reads its sub-path.
     *
     * @param array<string, mixed> $params Query parameters (unused).
     *
     * @return void
     */
    public function serve(array $params = []): void
    {
        unset($params);

        $page = $this->resolvePage();
        $file = $this->bundleRoot() . '/' . $page;

        if ($page === '' || !is_file($file)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Not found';
            return;
        }

        $html = file_get_contents($file);
        if ($html === false) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Could not read bundle page';
            return;
        }

        // The per-session CSRF token makes the shell uncacheable.
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, must-revalidate');
        echo $this->injectRuntime($html);
    }

    /**
     * Absolute path to the built bundle directory.
     *
     * @return string
     */
    private function bundleRoot(): string
    {
        $root = defined('LUKAISU_BASE_PATH')
            ? (string) constant('LUKAISU_BASE_PATH')
            : dirname(__DIR__, 3);
        return $root . '/' . self::BUNDLE_DIR;
    }

    /**
     * Map the request path under `/app/` to a safe `<name>.html` filename.
     *
     * `/app` and `/app/` default to the dashboard (`home.html`). Anything that
     * is not a flat `<name>.html` returns '' (→ 404), blocking path traversal.
     *
     * @return string
     */
    private function resolvePage(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim($path, '/');
        $path = UrlUtilities::stripBasePath($path);

        if ($path === '/app') {
            return 'home.html';
        }
        if (!str_starts_with($path, '/app/')) {
            return '';
        }
        $rest = substr($path, strlen('/app/'));
        if ($rest === '') {
            return 'home.html';
        }
        if (preg_match('/^[a-z0-9-]+\.html$/', $rest) !== 1) {
            return '';
        }
        return $rest;
    }

    /**
     * Inject the CSRF token, base path, and same-origin runtime config into the
     * bundle's `<head>` so the client authenticates against this origin's
     * `/api/v1` (see boot.ts `initDataMode` / client.ts `setSameOriginServerMode`).
     *
     * @param string $html The bundle page HTML.
     *
     * @return string
     */
    private function injectRuntime(string $html): string
    {
        $csrf = htmlspecialchars(CsrfMiddleware::getToken(), ENT_QUOTES);
        $basePath = htmlspecialchars(UrlUtilities::getBasePath(), ENT_QUOTES);
        $config = json_encode([
            'sameOriginServer' => true,
            'multiUser' => Globals::isMultiUserEnabled(),
        ]);
        if ($config === false) {
            $config = '{"sameOriginServer":true}';
        }

        $inject = "\n  <meta name=\"csrf-token\" content=\"{$csrf}\" />"
            . "\n  <meta name=\"lukaisu-base-path\" content=\"{$basePath}\" />"
            . "\n  <script type=\"application/json\" id=\"lukaisu-runtime-config\">"
            . "{$config}</script>\n";

        $pos = stripos($html, '</head>');
        if ($pos === false) {
            return $inject . $html;
        }
        return substr($html, 0, $pos) . $inject . substr($html, $pos);
    }
}
