<?php

/**
 * Review Module Service Provider
 *
 * Registers all services for the Review module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Review
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Review;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Domain
use Lukaisu\Modules\Review\Domain\ReviewRepositoryInterface;
// Infrastructure
use Lukaisu\Modules\Review\Infrastructure\MySqlReviewRepository;
use Lukaisu\Modules\Review\Infrastructure\SessionStateManager;
// Use Cases
use Lukaisu\Modules\Review\Application\UseCases\GetNextTerm;
use Lukaisu\Modules\Review\Application\UseCases\GetTableWords;
use Lukaisu\Modules\Review\Application\UseCases\GetReviewConfiguration;
use Lukaisu\Modules\Review\Application\UseCases\GetTomorrowCount;
use Lukaisu\Modules\Review\Application\UseCases\StartReviewSession;
use Lukaisu\Modules\Review\Application\UseCases\SubmitAnswer;
// Application
use Lukaisu\Modules\Review\Application\ReviewFacade;
// Http
use Lukaisu\Modules\Review\Http\ReviewApiHandler;
// Application Services
use Lukaisu\Modules\Review\Application\Services\ReviewService;

/**
 * Service provider for the Review module.
 *
 * Registers the ReviewRepositoryInterface, all use cases,
 * ReviewFacade and ReviewApiHandler.
 */
class ReviewServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register Repository Interface binding
        $container->singleton(ReviewRepositoryInterface::class, function (Container $_c) {
            return new MySqlReviewRepository();
        });

        // Register MySqlReviewRepository as concrete implementation
        $container->singleton(
            MySqlReviewRepository::class,
            /** @return MySqlReviewRepository */
            function (Container $c): MySqlReviewRepository {
                /** @var MySqlReviewRepository */
                return $c->getTyped(ReviewRepositoryInterface::class);
            }
        );

        // Register Infrastructure
        $container->singleton(SessionStateManager::class, function (Container $_c) {
            return new SessionStateManager();
        });

        // Register Use Cases
        $this->registerUseCases($container);

        // Register Facade
        $container->singleton(ReviewFacade::class, function (Container $c) {
            return new ReviewFacade(
                $c->getTyped(ReviewRepositoryInterface::class),
                $c->getTyped(SessionStateManager::class),
                $c->getTyped(GetNextTerm::class),
                $c->getTyped(GetTableWords::class),
                $c->getTyped(GetReviewConfiguration::class),
                $c->getTyped(GetTomorrowCount::class),
                $c->getTyped(StartReviewSession::class),
                $c->getTyped(SubmitAnswer::class)
            );
        });

        // Register API Handler
        $container->singleton(ReviewApiHandler::class, function (Container $c) {
            return new ReviewApiHandler(
                $c->getTyped(ReviewFacade::class)
            );
        });

        // Register ReviewService
        $container->singleton(ReviewService::class, function (Container $_c) {
            return new ReviewService();
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
        // GetNextTerm use case
        $container->singleton(GetNextTerm::class, function (Container $c) {
            return new GetNextTerm(
                $c->getTyped(ReviewRepositoryInterface::class)
            );
        });

        // GetTableWords use case
        $container->singleton(GetTableWords::class, function (Container $c) {
            return new GetTableWords(
                $c->getTyped(ReviewRepositoryInterface::class)
            );
        });

        // GetReviewConfiguration use case
        $container->singleton(GetReviewConfiguration::class, function (Container $c) {
            return new GetReviewConfiguration(
                $c->getTyped(ReviewRepositoryInterface::class),
                $c->getTyped(SessionStateManager::class)
            );
        });

        // GetTomorrowCount use case
        $container->singleton(GetTomorrowCount::class, function (Container $c) {
            return new GetTomorrowCount(
                $c->getTyped(ReviewRepositoryInterface::class)
            );
        });

        // StartReviewSession use case
        $container->singleton(StartReviewSession::class, function (Container $c) {
            return new StartReviewSession(
                $c->getTyped(ReviewRepositoryInterface::class),
                $c->getTyped(SessionStateManager::class)
            );
        });

        // SubmitAnswer use case
        $container->singleton(SubmitAnswer::class, function (Container $c) {
            return new SubmitAnswer(
                $c->getTyped(ReviewRepositoryInterface::class),
                $c->getTyped(SessionStateManager::class)
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Review module
    }
}
