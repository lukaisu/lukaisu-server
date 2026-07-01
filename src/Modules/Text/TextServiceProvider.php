<?php

/**
 * Text Module Service Provider
 *
 * Registers all services for the Text module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Domain
use Lukaisu\Modules\Text\Domain\TextRepositoryInterface;
// Infrastructure
use Lukaisu\Modules\Text\Infrastructure\MySqlTextRepository;
// Use Cases
use Lukaisu\Modules\Text\Application\UseCases\ImportText;
use Lukaisu\Modules\Text\Application\UseCases\UpdateText;
use Lukaisu\Modules\Text\Application\UseCases\ArchiveText;
use Lukaisu\Modules\Text\Application\UseCases\DeleteText;
use Lukaisu\Modules\Text\Application\UseCases\GetTextForReading;
use Lukaisu\Modules\Text\Application\UseCases\GetTextForEdit;
use Lukaisu\Modules\Text\Application\UseCases\ListTexts;
use Lukaisu\Modules\Text\Application\UseCases\ParseText;
use Lukaisu\Modules\Text\Application\UseCases\BuildTextFilters;
// Application
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Modules\Text\Application\Services\SentenceService;
// Http
use Lukaisu\Modules\Text\Http\TextController;
use Lukaisu\Modules\Text\Http\TextApiHandler;
use Lukaisu\Modules\Text\Http\WhisperApiHandler;
use Lukaisu\Modules\Text\Http\YouTubeApiHandler;
// Module services
use Lukaisu\Modules\Text\Application\Services\TextPrintService;
use Lukaisu\Modules\Text\Application\Services\TextScoringService;

/**
 * Service provider for the Text module.
 *
 * Registers the TextRepositoryInterface, all use cases,
 * TextFacade, TextController, and TextApiHandler.
 */
class TextServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register SentenceService
        $container->singleton(
            SentenceService::class,
            function (Container $_c) {
                return new SentenceService();
            }
        );

        // Register Repository Interface binding
        $container->singleton(
            TextRepositoryInterface::class,
            function (Container $_c) {
                return new MySqlTextRepository();
            }
        );

        // Register MySqlTextRepository as concrete implementation
        $container->singleton(
            MySqlTextRepository::class,
            function (Container $c): MySqlTextRepository {
                /**
            * @var MySqlTextRepository
            */
                return $c->getTyped(TextRepositoryInterface::class);
            }
        );

        // Register Use Cases
        $this->registerUseCases($container);

        // Register Facade
        $container->singleton(
            TextFacade::class,
            function (Container $c) {
                return new TextFacade(
                    $c->getTyped(TextRepositoryInterface::class)
                );
            }
        );

        // Register Controller
        $container->singleton(
            TextController::class,
            function (Container $c) {
                return new TextController(
                    $c->getTyped(TextFacade::class)
                );
            }
        );

        // Register API Handler
        $container->singleton(
            TextApiHandler::class,
            function (Container $_c) {
                return new TextApiHandler();
            }
        );

        // Register legacy services for backward compatibility
        $container->singleton(
            TextPrintService::class,
            function (Container $_c) {
                return new TextPrintService();
            }
        );

        // Text scoring service for difficulty/comprehensibility analysis
        $container->singleton(
            TextScoringService::class,
            function (Container $_c) {
                return new TextScoringService();
            }
        );

        // Register Whisper API Handler
        $container->singleton(WhisperApiHandler::class, function (Container $_c) {
            return new WhisperApiHandler();
        });

        // Register YouTube API Handler
        $container->singleton(YouTubeApiHandler::class, function (Container $_c) {
            return new YouTubeApiHandler();
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
        // ImportText use case
        $container->singleton(
            ImportText::class,
            function (Container $c) {
                return new ImportText(
                    $c->getTyped(TextRepositoryInterface::class)
                );
            }
        );

        // UpdateText use case
        $container->singleton(
            UpdateText::class,
            function (Container $_c) {
                return new UpdateText();
            }
        );

        // ArchiveText use case
        $container->singleton(
            ArchiveText::class,
            function (Container $_c) {
                return new ArchiveText();
            }
        );

        // DeleteText use case
        $container->singleton(
            DeleteText::class,
            function (Container $_c) {
                return new DeleteText();
            }
        );

        // GetTextForReading use case
        $container->singleton(
            GetTextForReading::class,
            function (Container $c) {
                return new GetTextForReading(
                    $c->getTyped(TextRepositoryInterface::class)
                );
            }
        );

        // GetTextForEdit use case
        $container->singleton(
            GetTextForEdit::class,
            function (Container $c) {
                return new GetTextForEdit(
                    $c->getTyped(TextRepositoryInterface::class)
                );
            }
        );

        // ListTexts use case
        $container->singleton(
            ListTexts::class,
            function (Container $c) {
                return new ListTexts(
                    $c->getTyped(TextRepositoryInterface::class)
                );
            }
        );

        // ParseText use case
        $container->singleton(
            ParseText::class,
            function (Container $_c) {
                return new ParseText();
            }
        );

        // BuildTextFilters use case (no dependencies)
        $container->singleton(
            BuildTextFilters::class,
            function (Container $_c) {
                return new BuildTextFilters();
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Text module
    }
}
