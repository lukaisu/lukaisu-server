<?php

/**
 * Activity Module Service Provider
 *
 * Registers all services for the Activity module.
 *
 * PHP version 8.2
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Activity
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Activity;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
use Lukaisu\Modules\Activity\Domain\ActivityRepositoryInterface;
use Lukaisu\Modules\Activity\Infrastructure\MySqlActivityRepository;
use Lukaisu\Modules\Activity\Application\ActivityFacade;
use Lukaisu\Modules\Activity\Http\ActivityApiHandler;

/**
 * Service provider for the Activity module.
 */
class ActivityServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        $container->singleton(ActivityRepositoryInterface::class, function (Container $_c) {
            return new MySqlActivityRepository();
        });

        $container->singleton(MySqlActivityRepository::class, function (Container $c): ActivityRepositoryInterface {
            return $c->getTyped(ActivityRepositoryInterface::class);
        });

        $container->singleton(ActivityFacade::class, function (Container $c) {
            return new ActivityFacade(
                $c->getTyped(ActivityRepositoryInterface::class)
            );
        });

        $container->singleton(ActivityApiHandler::class, function (Container $c) {
            return new ActivityApiHandler(
                $c->getTyped(ActivityFacade::class)
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed
    }
}
