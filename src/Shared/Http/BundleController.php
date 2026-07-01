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
     * Redirect a legacy Job-A page path to its bundle URL (the "cut-over").
     *
     * The reading/learning page routes (`/`, `/texts`, `/text/{id}/read`, …) no
     * longer render PHP views — they 302 to the equivalent bundle page under
     * `/app/`, mirroring the client-side `bundledPageFor()` in app/router.ts so a
     * direct hit / bookmark lands on the same page the in-app links resolve to.
     * Only GET navigations are routed here; the POST/JSON data routes on the
     * same paths keep their controllers.
     *
     * @param array<string, mixed> $params Query parameters (unused).
     *
     * @return void
     */
    public function redirect(array $params = []): void
    {
        unset($params);

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim($path, '/');
        $path = UrlUtilities::stripBasePath($path);
        $query = parse_url($uri, PHP_URL_QUERY);
        $query = is_string($query) ? $query : '';

        $page = $this->mapPathToBundlePage($path, $query);
        if ($page === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Not found';
            return;
        }

        header('Location: ' . UrlUtilities::getBasePath() . '/app/' . $page, true, 302);
    }

    /**
     * Map a legacy server path (+ query) to a bundle page like
     * `read.html?text=5`, or null when no bundle page covers it.
     *
     * Mirrors `bundledPageFor()` in src/frontend/app/router.ts.
     *
     * @param string $path  Base-path-stripped request path.
     * @param string $query Raw query string ('' when none).
     *
     * @return string|null
     */
    private function mapPathToBundlePage(string $path, string $query): ?string
    {
        $withQuery = static fn(string $page): string => $query !== '' ? $page . '?' . $query : $page;

        switch (true) {
            case $path === '/' || $path === '/index.php':
                return 'home.html';
            case $path === '/connect':
                return 'index.html';
            case $path === '/texts':
                return 'library.html';
            case $path === '/languages/new':
                return 'language.html';
            case $path === '/texts/new':
                return 'text.html';
            case $path === '/text/archived':
                return 'texts.html';
            case $path === '/text/check':
                return 'text-check.html';
            case $path === '/languages':
                return 'languages.html';
            case $path === '/tags' || $path === '/tags/term' || $path === '/tags/text':
                return 'tags.html';
            case $path === '/feeds' || $path === '/feeds/manage':
                return 'feeds.html';
            case $path === '/profile/preferences':
                return 'settings.html';
            case $path === '/profile/statistics':
                return 'statistics.html';
            case $path === '/words' || $path === '/words/edit':
                return $withQuery('words.html');
            case $path === '/word/bulk-translate':
                return $withQuery('bulk-translate.html');
            case $path === '/word/upload':
                return 'word-upload.html';
        }

        if (
            preg_match('#^/texts/(\d+)/edit$#', $path, $m) === 1
            || preg_match('#^/text/archived/(\d+)/edit$#', $path, $m) === 1
        ) {
            return 'text-edit.html?id=' . $m[1];
        }
        if (preg_match('#^/languages/(\d+)/edit$#', $path, $m) === 1) {
            return 'language-edit.html?id=' . $m[1];
        }
        if (preg_match('#^/languages/(\d+)/starter-vocab$#', $path, $m) === 1) {
            return 'starter-vocab.html?lang=' . $m[1];
        }
        if (preg_match('#^/words/(\d+)/edit$#', $path, $m) === 1) {
            return 'word.html?id=' . $m[1];
        }
        if (preg_match('#^/text/(\d+)/print-plain$#', $path, $m) === 1) {
            return 'text-print.html?text=' . $m[1];
        }
        if (preg_match('#^/text/(\d+)/read$#', $path, $m) === 1) {
            $lang = $this->queryValue($query, 'lang');
            return 'read.html?text=' . $m[1] . ($lang !== '' ? '&lang=' . rawurlencode($lang) : '');
        }
        if ($path === '/review') {
            $parts = [];
            foreach (['text', 'lang', 'selection'] as $key) {
                $value = $this->queryValue($query, $key);
                if ($value !== '') {
                    $parts[] = $key . '=' . rawurlencode($value);
                }
            }
            return $parts === [] ? 'review.html' : 'review.html?' . implode('&', $parts);
        }
        // Legacy query-string forms the server still routes.
        if ($path === '/text/read' || $path === '/text/print-plain') {
            $text = $this->queryValue($query, 'text');
            if ($text !== '') {
                $page = $path === '/text/read' ? 'read.html' : 'text-print.html';
                return $page . '?text=' . rawurlencode($text);
            }
        }

        return null;
    }

    /**
     * Read a single value out of a raw query string (no superglobal access).
     *
     * @param string $query Raw query string.
     * @param string $key   Parameter name.
     *
     * @return string Value, or '' when absent.
     */
    private function queryValue(string $query, string $key): string
    {
        if ($query === '') {
            return '';
        }
        /** @var array<string, mixed> $parsed */
        $parsed = [];
        parse_str($query, $parsed);
        return isset($parsed[$key]) && is_string($parsed[$key]) ? $parsed[$key] : '';
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
