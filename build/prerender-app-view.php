<?php

/**
 * Prerender bundled app pages from their PHP views.
 *
 * Part of the "PHP View Retirement" plan (docs-src/server/php-view-retirement.md):
 * each Job-A bundle page that *mounts an existing Alpine component* gets its
 * static body from the matching server view, with `__()` resolved against the
 * English locale and `IconHelper`/`PageLayoutHelper` rendered to static markup —
 * exactly what the (deleted) build/php-view-prerender.mjs did, but in PHP so the
 * real helpers run unchanged.
 *
 * The output is committed as `src/frontend/app/<page>.html`; the app owns it
 * thereafter (PHP is frozen). Re-run this only when a source view changes.
 *
 *   php build/prerender-app-view.php          # regenerate every registered page
 *   php build/prerender-app-view.php words    # regenerate one page
 *
 * To add a page, append an entry to $PAGES (below). This covers the
 * "mount a component" pages only; purpose-built API-form pages (language/text)
 * are hand-authored, not prerendered.
 *
 * @license Unlicense <http://unlicense.org/>
 */

declare(strict_types=1);

namespace {
    $REPO = dirname(__DIR__);

    /** Registered app pages: page id => view + shell metadata. */
    $PAGES = [
        'words' => [
            'view' => 'src/Modules/Vocabulary/Views/list_alpine.php',
            'title' => 'Terms',
            'modules' => 'vocabulary,language',
            'currentPage' => 'terms',
            'entry' => './words.ts',
            // Baked config defaults; overwritten at runtime by the page's TS via
            // injectConfig(), so these only matter if the bundle JS fails to load.
            'vars' => ['currentlang' => 0, 'perPage' => 50],
        ],
        'languages' => [
            'view' => 'src/Modules/Language/Views/index.php',
            'title' => 'Languages',
            'modules' => 'language',
            'currentPage' => 'languages',
            'entry' => './languages.ts',
            // The view reads everything it shows from the API at runtime; it needs
            // no injected vars (only UrlUtilities::getBasePath(), stubbed below).
            'vars' => [],
        ],
    ];

    // --- English-locale-backed __() (namespace prefix selects the locale file).
    $GLOBALS['LOCALE'] = [];
    foreach (glob("$REPO/locale/en/*.json") ?: [] as $file) {
        $ns = basename($file, '.json');
        $GLOBALS['LOCALE'][$ns] = json_decode((string) file_get_contents($file), true) ?: [];
    }

    function __(string $key, array $params = []): string
    {
        $dot = strpos($key, '.');
        if ($dot === false) {
            return $key;
        }
        $value = $GLOBALS['LOCALE'][substr($key, 0, $dot)][substr($key, $dot + 1)] ?? $key;
        foreach ($params as $k => $v) {
            $value = str_replace('{' . $k . '}', (string) $v, $value);
        }
        return $value;
    }

    require "$REPO/src/Shared/UI/Helpers/IconHelper.php";

    /**
     * Render a view file to its body HTML with the given template variables.
     *
     * @param array<string, mixed> $vars
     */
    function renderViewBody(string $absViewPath, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $absViewPath;
        return rtrim((string) ob_get_clean(), "\n");
    }

    /** Wrap a prerendered body in the standard bundled-page shell. */
    function wrapPage(string $body, array $meta): string
    {
        $title = htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8');
        $modules = htmlspecialchars($meta['modules'], ENT_QUOTES, 'UTF-8');
        $current = htmlspecialchars($meta['currentPage'], ENT_QUOTES, 'UTF-8');
        $entry = htmlspecialchars($meta['entry'], ENT_QUOTES, 'UTF-8');
        $view = htmlspecialchars($meta['view'], ENT_QUOTES, 'UTF-8');
        $body = preg_replace('/^/m', '    ', $body);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="color-scheme" content="light dark" />
  <title>Lukaisu — {$title}</title>
  <meta name="lukaisu-modules" content="{$modules}" />
  <style>[x-cloak]{display:none !important}</style>
</head>
<body>
  <!-- Real navbar, hydrated from GET /api/v1/navbar (mountNavbar in main.ts). -->
  <div id="navbar-root" data-navbar-root data-current-page="{$current}"></div>
  <section class="section pt-4">
    <h1 class="title is-4">{$title}</h1>
    <!-- Body prerendered from {$view}.
         Regenerate: php build/prerender-app-view.php -->
{$body}
  </section>
  <script type="module" src="{$entry}"></script>
</body>
</html>

HTML;
    }
}

// --- PageLayoutHelper: only the methods the prerendered views call. url() is
//     identity (the bundle uses server-relative paths; the link router resolves
//     them at click time). Extend as more pages are added to $PAGES.
namespace Lukaisu\Shared\UI\Helpers {
    class PageLayoutHelper
    {
        public static function buildActionCard(array $actions): string
        {
            $buttonsHtml = '';
            foreach ($actions as $action) {
                $url = htmlspecialchars($action['url'], ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8');
                $icon = isset($action['icon']) ? IconHelper::render($action['icon'], ['alt' => $label]) : '';
                $class = isset($action['class']) ? ' ' . htmlspecialchars($action['class'], ENT_QUOTES, 'UTF-8') : '';
                $target = isset($action['target'])
                    ? ' target="' . htmlspecialchars($action['target'], ENT_QUOTES, 'UTF-8') . '"'
                    : '';
                $attrs = isset($action['attrs']) ? ' ' . $action['attrs'] : '';

                $buttonsHtml .= <<<HTML
                <a href="{$url}" class="button is-light{$class}"{$target}{$attrs}>
                    <span class="icon">{$icon}</span>
                    <span>{$label}</span>
                </a>
HTML;
            }

            return <<<HTML
<div class="card action-card mb-4">
    <div class="card-content">
        <div class="buttons is-centered">
            {$buttonsHtml}
        </div>
    </div>
</div>
HTML;
        }
    }
}

// --- UrlUtilities: getBasePath() is empty in the bundle. The views build
//     server-relative links ("/languages/new"); the client link router resolves
//     them at click time, so no base prefix is baked into the static HTML.
namespace Lukaisu\Shared\Infrastructure\Http {
    class UrlUtilities
    {
        public static function getBasePath(): string
        {
            return '';
        }
    }
}

namespace {
    $only = $argv[1] ?? null;
    $appDir = "$REPO/src/frontend/app";

    foreach ($PAGES as $id => $meta) {
        if ($only !== null && $only !== $id) {
            continue;
        }
        $body = renderViewBody("$REPO/{$meta['view']}", $meta['vars']);
        $html = wrapPage($body, $meta);
        $out = "$appDir/$id.html";
        file_put_contents($out, $html);
        fwrite(STDERR, "prerendered $id -> src/frontend/app/$id.html\n");
    }
}
