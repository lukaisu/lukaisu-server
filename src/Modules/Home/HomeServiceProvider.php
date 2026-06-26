<?php

/**
 * Home Module Service Provider
 *
 * Registers all services for the Home module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Home
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Home;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Application
use Lukaisu\Modules\Home\Application\HomeFacade;
use Lukaisu\Modules\Home\Application\UseCases\GetDashboardData;
use Lukaisu\Modules\Home\Application\UseCases\GetTextStatistics;
// Http
use Lukaisu\Modules\Home\Http\HomeController;
// Dependencies
use Lukaisu\Modules\Language\Application\LanguageFacade;

/**
 * Service provider for the Home module.
 *
 * Registers facade, use cases, and controller for the Home module.
 */
class HomeServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register Use Cases
        $this->registerUseCases($container);

        // Register Facade
        $container->singleton(HomeFacade::class, function (Container $_c) {
            return new HomeFacade();
        });

        // Register Controller
        $container->bind(HomeController::class, function (Container $c) {
            return new HomeController(
                $c->getTyped(HomeFacade::class),
                $c->getTyped(LanguageFacade::class)
            );
        });
    }

    /**
     * Register use cases.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    private function registerUseCases(Container $container): void
    {
        $container->singleton(GetDashboardData::class, function (Container $_c) {
            return new GetDashboardData();
        });

        $container->singleton(GetTextStatistics::class, function (Container $_c) {
            return new GetTextStatistics();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Home module
    }
}
