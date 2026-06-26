<?php

/**
 * Service Provider Interface
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Shared\Infrastructure\Container
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Infrastructure\Container;

/**
 * Interface for service providers that register services in the container.
 *
 * Service providers are a way to organize service registration and
 * bootstrapping logic.
 */
interface ServiceProviderInterface
{
    /**
     * Register services in the container.
     *
     * This method is called during application bootstrap.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    public function register(Container $container): void;

    /**
     * Bootstrap any application services.
     *
     * This method is called after all providers have been registered.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    public function boot(Container $container): void;
}
