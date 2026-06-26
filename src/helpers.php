<?php

/**
 * Global helper functions for Lukaisu Server.
 *
 * These functions are available throughout the application and provide
 * convenient shortcuts for common operations.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

use Lukaisu\Shared\Infrastructure\Http\UrlUtilities;
use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\I18n\Translator;

if (!function_exists('url')) {
    /**
     * Generate a URL with the application base path prepended.
     *
     * Use this for all internal links to ensure they work correctly
     * when the application is installed in a subdirectory.
     *
     * @param string $path The path to generate URL for (should start with /)
     *
     * @return string The full URL path with base path prepended
     *
     * @example url('/login') returns '/lukaisu-server/login' if APP_BASE_PATH=/lukaisu-server
     * @example url('/') returns '/lukaisu-server' if APP_BASE_PATH=/lukaisu-server
     * @example url('/assets/css/main.css') returns '/lukaisu-server/assets/css/main.css'
     */
    function url(string $path = '/'): string
    {
        return UrlUtilities::url($path);
    }
}

if (!function_exists('__')) {
    /**
     * Translate a key using the i18n translator.
     *
     * @param string                    $key    Dot-notated translation key (e.g. "common.save")
     * @param array<string, string|int> $params Interpolation parameters
     *
     * @return string Translated string, or the raw key if translator is unavailable
     */
    function __(string $key, array $params = []): string
    {
        $container = Container::getInstance();
        try {
            if ($container->has(Translator::class)) {
                return $container->getTyped(Translator::class)->translate($key, $params);
            }
        } catch (\Throwable $e) {
            // Translator unavailable (e.g. in unit tests with no locale path bound)
        }
        return $key;
    }
}

if (!function_exists('__e')) {
    /**
     * Translate a key and HTML-escape the result for safe output in templates.
     *
     * @param string                    $key    Dot-notated translation key
     * @param array<string, string|int> $params Interpolation parameters
     *
     * @return string HTML-escaped translated string
     */
    function __e(string $key, array $params = []): string
    {
        return htmlspecialchars(__($key, $params), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the configured application base path.
     *
     * @return string The base path (e.g., '/lukaisu-server') or empty string for root
     */
    function base_path(): string
    {
        return UrlUtilities::getBasePath();
    }
}
