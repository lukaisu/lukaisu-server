<?php

/**
 * Dictionary Module Service Provider
 *
 * Registers all services for the Dictionary module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Dictionary
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Dictionary;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Application
use Lukaisu\Modules\Dictionary\Application\DictionaryFacade;
use Lukaisu\Modules\Dictionary\Application\TranslationService;
// Http
use Lukaisu\Modules\Dictionary\Http\DictionaryApiHandler;
use Lukaisu\Modules\Dictionary\Http\DictionaryController;
use Lukaisu\Modules\Dictionary\Http\TranslationController;
// Application Services
use Lukaisu\Modules\Dictionary\Application\Services\CuratedDictImportService;
use Lukaisu\Modules\Dictionary\Application\Services\LocalDictionaryService;
// Infrastructure - Dictionary Importers
use Lukaisu\Modules\Dictionary\Infrastructure\Import\ImporterInterface;
use Lukaisu\Modules\Dictionary\Infrastructure\Import\CsvImporter;
use Lukaisu\Modules\Dictionary\Infrastructure\Import\JsonImporter;
use Lukaisu\Modules\Dictionary\Infrastructure\Import\StarDictImporter;
// Language Module

/**
 * Service provider for the Dictionary module.
 *
 * Registers the facade, controller, and related services
 * for the Dictionary module.
 */
class DictionaryServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register LocalDictionaryService (used by facade)
        $container->singleton(LocalDictionaryService::class, function (Container $_c) {
            return new LocalDictionaryService();
        });

        // Register Facade
        $container->singleton(DictionaryFacade::class, function (Container $c) {
            return new DictionaryFacade(
                $c->getTyped(LocalDictionaryService::class)
            );
        });

        // Register Curated Import Service
        $container->singleton(CuratedDictImportService::class, function (Container $c) {
            return new CuratedDictImportService(
                $c->getTyped(DictionaryFacade::class)
            );
        });

        // Register API Handler
        $container->singleton(DictionaryApiHandler::class, function (Container $c) {
            return new DictionaryApiHandler(
                $c->getTyped(DictionaryFacade::class),
                $c->getTyped(CuratedDictImportService::class)
            );
        });

        // Register Controller
        $container->singleton(DictionaryController::class, function (Container $c) {
            return new DictionaryController(
                $c->getTyped(DictionaryFacade::class)
            );
        });

        // Register TranslationService
        $container->singleton(TranslationService::class, function (Container $_c) {
            return new TranslationService();
        });

        // Register TranslationController
        $container->singleton(TranslationController::class, function (Container $c) {
            return new TranslationController(
                $c->getTyped(TranslationService::class)
            );
        });

        // Register Dictionary Importers
        $container->singleton(CsvImporter::class, function (Container $_c) {
            return new CsvImporter();
        });

        $container->singleton(JsonImporter::class, function (Container $_c) {
            return new JsonImporter();
        });

        $container->singleton(StarDictImporter::class, function (Container $_c) {
            return new StarDictImporter();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Dictionary module
    }
}
