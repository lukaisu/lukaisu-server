<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Shared\I18n;

use Lukaisu\Shared\I18n\Translator;
use Lukaisu\Shared\I18n\TranslatorServiceProvider;
use Lukaisu\Shared\Infrastructure\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the guest UI-language override (login-screen language switcher).
 *
 * Uses a throwaway locale fixture with two locales so getAvailableLocales()
 * is deterministic. The DB-backed app_language lookup in boot() is unavailable
 * here and is swallowed, leaving the override as the only signal.
 */
class TranslatorServiceProviderTest extends TestCase
{
    private string $localePath = '';

    protected function setUp(): void
    {
        unset($_GET['lang'], $_COOKIE['lukaisu_lang']);
        $this->localePath = sys_get_temp_dir() . '/lukaisu-i18n-prov-' . uniqid('', true);
        foreach (['en', 'fr'] as $loc) {
            @mkdir($this->localePath . '/' . $loc, 0777, true);
            file_put_contents($this->localePath . '/' . $loc . '/common.json', '{"x":"y"}');
        }
    }

    protected function tearDown(): void
    {
        unset($_GET['lang'], $_COOKIE['lukaisu_lang']);
        @unlink($this->localePath . '/en/common.json');
        @unlink($this->localePath . '/fr/common.json');
        @rmdir($this->localePath . '/en');
        @rmdir($this->localePath . '/fr');
        @rmdir($this->localePath);
    }

    private function containerWithTranslator(): Container
    {
        $container = new Container();
        $path = $this->localePath;
        $container->singleton(Translator::class, static fn() => new Translator($path, 'en'));
        return $container;
    }

    private function bootLocale(Container $container): string
    {
        (new TranslatorServiceProvider())->boot($container);
        return $container->getTyped(Translator::class)->getLocale();
    }

    public function testCookieLocaleOverrideApplied(): void
    {
        $_COOKIE['lukaisu_lang'] = 'fr';
        $this->assertSame('fr', $this->bootLocale($this->containerWithTranslator()));
    }

    public function testInvalidLocaleOverrideIsIgnored(): void
    {
        // A value that is not an installed locale must never be applied.
        $_COOKIE['lukaisu_lang'] = 'zz';
        $this->assertSame('en', $this->bootLocale($this->containerWithTranslator()));
    }

    public function testNoOverrideKeepsDefault(): void
    {
        $this->assertSame('en', $this->bootLocale($this->containerWithTranslator()));
    }
}
