<?php

/**
 * Feed Module Service Provider
 *
 * Registers all services for the Feed module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Feed
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Feed;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Domain
use Lukaisu\Modules\Feed\Domain\FeedRepositoryInterface;
use Lukaisu\Modules\Feed\Domain\ArticleRepositoryInterface;
use Lukaisu\Modules\Feed\Domain\TextCreationInterface;
// Infrastructure
use Lukaisu\Modules\Feed\Infrastructure\MySqlFeedRepository;
use Lukaisu\Modules\Feed\Infrastructure\MySqlArticleRepository;
use Lukaisu\Modules\Feed\Infrastructure\TextCreationAdapter;
// Shared Infrastructure
use Lukaisu\Shared\Infrastructure\Http\FlashMessageService;
// Services
use Lukaisu\Modules\Feed\Application\Services\RssParser;
use Lukaisu\Modules\Feed\Application\Services\ArticleExtractor;
// Use Cases
use Lukaisu\Modules\Feed\Application\UseCases\CreateFeed;
use Lukaisu\Modules\Feed\Application\UseCases\UpdateFeed;
use Lukaisu\Modules\Feed\Application\UseCases\DeleteFeeds;
use Lukaisu\Modules\Feed\Application\UseCases\LoadFeed;
use Lukaisu\Modules\Feed\Application\UseCases\GetFeedList;
use Lukaisu\Modules\Feed\Application\UseCases\GetFeedById;
use Lukaisu\Modules\Feed\Application\UseCases\GetArticles;
use Lukaisu\Modules\Feed\Application\UseCases\ImportArticles;
use Lukaisu\Modules\Feed\Application\UseCases\DeleteArticles;
use Lukaisu\Modules\Feed\Application\UseCases\ResetErrorArticles;
// Application
use Lukaisu\Modules\Feed\Application\FeedFacade;
// Language Module
use Lukaisu\Modules\Language\Application\LanguageFacade;
// Http
use Lukaisu\Modules\Feed\Http\FeedController;
use Lukaisu\Modules\Feed\Http\FeedApiHandler;

/**
 * Service provider for the Feed module.
 *
 * Registers repositories, services, use cases, facade,
 * controller, and API handler for the Feed module.
 */
class FeedServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register Repository Interface bindings
        $this->registerRepositories($container);

        // Register Services
        $this->registerServices($container);

        // Register Use Cases
        $this->registerUseCases($container);

        // Register Facade
        $container->singleton(FeedFacade::class, function (Container $c) {
            return new FeedFacade(
                $c->getTyped(FeedRepositoryInterface::class),
                $c->getTyped(ArticleRepositoryInterface::class),
                $c->getTyped(TextCreationInterface::class),
                $c->getTyped(RssParser::class),
                $c->getTyped(ArticleExtractor::class)
            );
        });

        $container->singleton(FlashMessageService::class, function (Container $_c) {
            return new FlashMessageService();
        });

        // Register Controller
        $container->singleton(FeedController::class, function (Container $c) {
            return new FeedController(
                $c->getTyped(FeedFacade::class),
                $c->getTyped(LanguageFacade::class),
                $c->getTyped(FlashMessageService::class)
            );
        });

        // Register API Handler
        $container->singleton(FeedApiHandler::class, function (Container $c) {
            return new FeedApiHandler(
                $c->getTyped(FeedFacade::class)
            );
        });
    }

    /**
     * Register repository bindings.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    private function registerRepositories(Container $container): void
    {
        // Feed Repository
        $container->singleton(FeedRepositoryInterface::class, function (Container $_c) {
            return new MySqlFeedRepository();
        });

        $container->singleton(MySqlFeedRepository::class, function (Container $c): FeedRepositoryInterface {
            return $c->getTyped(FeedRepositoryInterface::class);
        });

        // Article Repository
        $container->singleton(ArticleRepositoryInterface::class, function (Container $_c) {
            return new MySqlArticleRepository();
        });

        $container->singleton(MySqlArticleRepository::class, function (Container $c): ArticleRepositoryInterface {
            return $c->getTyped(ArticleRepositoryInterface::class);
        });

        // Text Creation Adapter
        $container->singleton(TextCreationInterface::class, function (Container $_c) {
            return new TextCreationAdapter();
        });

        $container->singleton(TextCreationAdapter::class, function (Container $c): TextCreationInterface {
            return $c->getTyped(TextCreationInterface::class);
        });
    }

    /**
     * Register application services.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    private function registerServices(Container $container): void
    {
        $container->singleton(RssParser::class, function (Container $_c) {
            return new RssParser();
        });

        $container->singleton(ArticleExtractor::class, function (Container $_c) {
            return new ArticleExtractor();
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
        // CreateFeed use case
        $container->singleton(CreateFeed::class, function (Container $c) {
            return new CreateFeed(
                $c->getTyped(FeedRepositoryInterface::class)
            );
        });

        // UpdateFeed use case
        $container->singleton(UpdateFeed::class, function (Container $c) {
            return new UpdateFeed(
                $c->getTyped(FeedRepositoryInterface::class)
            );
        });

        // DeleteFeeds use case
        $container->singleton(DeleteFeeds::class, function (Container $c) {
            return new DeleteFeeds(
                $c->getTyped(FeedRepositoryInterface::class),
                $c->getTyped(ArticleRepositoryInterface::class)
            );
        });

        // LoadFeed use case
        $container->singleton(LoadFeed::class, function (Container $c) {
            return new LoadFeed(
                $c->getTyped(FeedRepositoryInterface::class),
                $c->getTyped(ArticleRepositoryInterface::class),
                $c->getTyped(RssParser::class)
            );
        });

        // GetFeedList use case
        $container->singleton(GetFeedList::class, function (Container $c) {
            return new GetFeedList(
                $c->getTyped(FeedRepositoryInterface::class),
                $c->getTyped(ArticleRepositoryInterface::class)
            );
        });

        // GetFeedById use case
        $container->singleton(GetFeedById::class, function (Container $c) {
            return new GetFeedById(
                $c->getTyped(FeedRepositoryInterface::class)
            );
        });

        // GetArticles use case
        $container->singleton(GetArticles::class, function (Container $c) {
            return new GetArticles(
                $c->getTyped(ArticleRepositoryInterface::class)
            );
        });

        // ImportArticles use case
        $container->singleton(ImportArticles::class, function (Container $c) {
            return new ImportArticles(
                $c->getTyped(ArticleRepositoryInterface::class),
                $c->getTyped(FeedRepositoryInterface::class),
                $c->getTyped(TextCreationInterface::class),
                $c->getTyped(ArticleExtractor::class)
            );
        });

        // DeleteArticles use case
        $container->singleton(DeleteArticles::class, function (Container $c) {
            return new DeleteArticles(
                $c->getTyped(ArticleRepositoryInterface::class),
                $c->getTyped(FeedRepositoryInterface::class)
            );
        });

        // ResetErrorArticles use case
        $container->singleton(ResetErrorArticles::class, function (Container $c) {
            return new ResetErrorArticles(
                $c->getTyped(ArticleRepositoryInterface::class)
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Feed module
    }
}
