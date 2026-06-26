<?php

/**
 * Language Module Service Provider
 *
 * Registers all services for the Language module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Language
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Language;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Domain
use Lukaisu\Modules\Language\Domain\LanguageRepositoryInterface;
// Infrastructure
use Lukaisu\Modules\Language\Infrastructure\MySqlLanguageRepository;
// Use Cases
use Lukaisu\Modules\Language\Application\UseCases\CreateLanguage;
use Lukaisu\Modules\Language\Application\UseCases\UpdateLanguage;
use Lukaisu\Modules\Language\Application\UseCases\DeleteLanguage;
use Lukaisu\Modules\Language\Application\UseCases\GetLanguageById;
use Lukaisu\Modules\Language\Application\UseCases\GetLanguageCode;
use Lukaisu\Modules\Language\Application\UseCases\GetPhoneticReading;
use Lukaisu\Modules\Language\Application\UseCases\ListLanguages;
use Lukaisu\Modules\Language\Application\UseCases\ReparseLanguageTexts;
// Application
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Modules\Language\Application\Services\TextParsingService;
// Http
use Lukaisu\Modules\Language\Http\LanguageController;
use Lukaisu\Modules\Language\Http\LanguageApiHandler;
// Cross-module
use Lukaisu\Modules\Dictionary\Application\DictionaryFacade;
// NLP Service
use Lukaisu\Modules\Language\Infrastructure\NlpServiceHandler;
// Parser Infrastructure
use Lukaisu\Modules\Language\Infrastructure\Parser\ExternalParserLoader;
use Lukaisu\Modules\Language\Infrastructure\Parser\ParserRegistry;
use Lukaisu\Modules\Language\Application\Services\ParsingCoordinator;

/**
 * Service provider for the Language module.
 *
 * Registers the LanguageRepositoryInterface, all use cases,
 * LanguageFacade, LanguageController, and LanguageApiHandler.
 */
class LanguageServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register NLP Service Handler
        $container->singleton(NlpServiceHandler::class, function (Container $_c) {
            return new NlpServiceHandler();
        });

        // Register TextParsingService
        $container->singleton(TextParsingService::class, function (Container $_c) {
            return new TextParsingService();
        });

        // Register Parser Infrastructure
        $container->singleton(ExternalParserLoader::class, function (Container $_c) {
            return new ExternalParserLoader();
        });

        $container->singleton(ParserRegistry::class, function (Container $c) {
            return new ParserRegistry(
                $c->getTyped(ExternalParserLoader::class)
            );
        });

        $container->singleton(ParsingCoordinator::class, function (Container $c) {
            return new ParsingCoordinator(
                $c->getTyped(ParserRegistry::class)
            );
        });

        // Register Repository Interface binding
        $container->singleton(LanguageRepositoryInterface::class, function (Container $_c) {
            return new MySqlLanguageRepository();
        });

        // Register MySqlLanguageRepository as concrete implementation
        $container->singleton(
            MySqlLanguageRepository::class,
            /** @return MySqlLanguageRepository */
            function (Container $c): MySqlLanguageRepository {
                /** @var MySqlLanguageRepository */
                return $c->getTyped(LanguageRepositoryInterface::class);
            }
        );

        // Register Use Cases
        $this->registerUseCases($container);

        // Register Facade
        $container->singleton(LanguageFacade::class, function (Container $c) {
            return new LanguageFacade(
                $c->getTyped(LanguageRepositoryInterface::class)
            );
        });

        // Register Controller
        $container->singleton(LanguageController::class, function (Container $c) {
            return new LanguageController(
                $c->getTyped(LanguageFacade::class),
                $c->getTyped(DictionaryFacade::class)
            );
        });

        // Register API Handler
        $container->singleton(LanguageApiHandler::class, function (Container $c) {
            return new LanguageApiHandler(
                $c->getTyped(LanguageFacade::class)
            );
        });
    }

    /**
     * Register all use case classes.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    private function registerUseCases(Container $container): void
    {
        // GetLanguageById use case
        $container->singleton(GetLanguageById::class, function (Container $c) {
            return new GetLanguageById(
                $c->getTyped(LanguageRepositoryInterface::class)
            );
        });

        // ListLanguages use case
        $container->singleton(ListLanguages::class, function (Container $c) {
            return new ListLanguages(
                $c->getTyped(LanguageRepositoryInterface::class)
            );
        });

        // CreateLanguage use case
        $container->singleton(CreateLanguage::class, function (Container $_c) {
            return new CreateLanguage();
        });

        // ReparseLanguageTexts use case
        $container->singleton(ReparseLanguageTexts::class, function (Container $_c) {
            return new ReparseLanguageTexts();
        });

        // UpdateLanguage use case (depends on ReparseLanguageTexts)
        $container->singleton(UpdateLanguage::class, function (Container $c) {
            return new UpdateLanguage(
                $c->getTyped(ReparseLanguageTexts::class)
            );
        });

        // DeleteLanguage use case
        $container->singleton(DeleteLanguage::class, function (Container $_c) {
            return new DeleteLanguage();
        });

        // GetLanguageCode use case
        $container->singleton(GetLanguageCode::class, function (Container $c) {
            return new GetLanguageCode(
                $c->getTyped(LanguageRepositoryInterface::class)
            );
        });

        // GetPhoneticReading use case
        $container->singleton(GetPhoneticReading::class, function (Container $c) {
            return new GetPhoneticReading(
                $c->getTyped(LanguageRepositoryInterface::class)
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Language module
    }
}
