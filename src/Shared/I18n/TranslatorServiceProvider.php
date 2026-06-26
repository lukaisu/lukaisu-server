<?php

/**
 * Translator Service Provider
 *
 * Registers the Translator service in the DI container and configures
 * the active locale from the user's app_language setting.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Shared\I18n
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Shared\I18n;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
use Lukaisu\Shared\Infrastructure\Database\Settings;

/**
 * Registers the Translator as a singleton and sets the active locale
 * from the database during the boot phase.
 */
class TranslatorServiceProvider implements ServiceProviderInterface
{
    /**
     * Register the Translator singleton.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton(Translator::class, function () {
            $localePath = getcwd() . '/locale';
            return new Translator($localePath, 'en');
        });
    }

    /**
     * Read the user's locale preference from settings and apply it.
     *
     * Runs after all providers are registered, so the DB is available.
     * Silently defaults to English if the DB is not yet configured
     * (e.g. during the setup wizard).
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    public function boot(Container $container): void
    {
        $translator = $container->getTyped(Translator::class);

        try {
            $locale = Settings::getWithDefault('app_language');
            if ($locale !== '') {
                $translator->setLocale($locale);
            }
        } catch (\Throwable $e) {
            // DB not available (e.g. wizard page) — stay with English
        }

        // An explicit choice (the login-screen language switcher) overrides the
        // configured app language. Works even when the DB is unavailable, so a
        // guest can read the login/setup pages in their language.
        $override = $this->resolveLocaleOverride($translator);
        if ($override !== null) {
            $translator->setLocale($override);
        }
    }

    /**
     * Resolve an explicit UI-language override from the request.
     *
     * Precedence: a `?lang=` query parameter (which is also persisted to the
     * `lukaisu_lang` cookie) over an existing `lukaisu_lang` cookie. Both are validated
     * against the available locales so only a real locale can be selected.
     *
     * @param Translator $translator Used to list the available locales.
     *
     * @return string|null The chosen locale, or null when none/invalid.
     */
    private function resolveLocaleOverride(Translator $translator): ?string
    {
        $available = $translator->getAvailableLocales();

        $queryLang = $_GET['lang'] ?? null;
        if (is_string($queryLang) && in_array($queryLang, $available, true)) {
            // Persist for subsequent pages (a year), if headers allow.
            if (!headers_sent()) {
                setcookie('lukaisu_lang', $queryLang, [
                    'expires' => time() + 31536000,
                    'path' => '/',
                    'samesite' => 'Lax',
                ]);
            }
            return $queryLang;
        }

        $cookieLang = $_COOKIE['lukaisu_lang'] ?? null;
        if (is_string($cookieLang) && in_array($cookieLang, $available, true)) {
            return $cookieLang;
        }

        return null;
    }
}
