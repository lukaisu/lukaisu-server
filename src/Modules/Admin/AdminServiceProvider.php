<?php

/**
 * Admin Module Service Provider
 *
 * Registers all services for the Admin module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Domain
use Lukaisu\Modules\Admin\Domain\SettingsRepositoryInterface;
use Lukaisu\Modules\Admin\Domain\BackupRepositoryInterface;
// Infrastructure
use Lukaisu\Modules\Admin\Infrastructure\MySqlSettingsRepository;
use Lukaisu\Modules\Admin\Infrastructure\MySqlBackupRepository;
use Lukaisu\Modules\Admin\Infrastructure\MySqlStatisticsRepository;
use Lukaisu\Modules\Admin\Infrastructure\FileSystemEnvRepository;
// Application
use Lukaisu\Modules\Admin\Application\AdminFacade;
use Lukaisu\Modules\Admin\Application\Services\SessionCleaner;
// Http
use Lukaisu\Modules\Admin\Http\AdminApiHandler;
// Application Services
use Lukaisu\Modules\Admin\Application\Services\TtsService;
// User Management Use Cases
use Lukaisu\Modules\Admin\Application\UseCases\UserManagement\ListUsers;
use Lukaisu\Modules\Admin\Application\UseCases\UserManagement\CreateUser;
use Lukaisu\Modules\Admin\Application\UseCases\UserManagement\UpdateUser;
use Lukaisu\Modules\Admin\Application\UseCases\UserManagement\DeleteUser;
use Lukaisu\Modules\Admin\Application\UseCases\UserManagement\ToggleUserStatus;
use Lukaisu\Modules\Admin\Application\UseCases\UserManagement\ToggleUserRole;
// Cross-module dependencies
use Lukaisu\Modules\User\Domain\UserRepositoryInterface;
use Lukaisu\Modules\User\Infrastructure\MySqlUserRepository;
use Lukaisu\Modules\User\Application\Services\PasswordHasher;

/**
 * Service provider for the Admin module.
 *
 * Registers repositories, services, facade, controller,
 * and API handler for the Admin module.
 */
class AdminServiceProvider implements ServiceProviderInterface
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

        // Register Facade
        $container->singleton(AdminFacade::class, function (Container $c) {
            return new AdminFacade(
                $c->getTyped(SettingsRepositoryInterface::class),
                $c->getTyped(BackupRepositoryInterface::class)
            );
        });

        // Register API Handler
        $container->singleton(AdminApiHandler::class, function (Container $c) {
            return new AdminApiHandler(
                $c->getTyped(AdminFacade::class)
            );
        });

        // Register User Management Use Cases and Controller
        $this->registerUserManagement($container);
    }

    /**
     * Register user management use cases and controller.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    private function registerUserManagement(Container $container): void
    {
        $container->singleton(ListUsers::class, function (Container $c) {
            return new ListUsers(
                $c->getTyped(MySqlUserRepository::class)
            );
        });

        $container->singleton(CreateUser::class, function (Container $c) {
            return new CreateUser(
                $c->getTyped(UserRepositoryInterface::class),
                $c->getTyped(PasswordHasher::class)
            );
        });

        $container->singleton(UpdateUser::class, function (Container $c) {
            return new UpdateUser(
                $c->getTyped(UserRepositoryInterface::class),
                $c->getTyped(PasswordHasher::class)
            );
        });

        $container->singleton(DeleteUser::class, function (Container $c) {
            return new DeleteUser(
                $c->getTyped(UserRepositoryInterface::class)
            );
        });

        $container->singleton(ToggleUserStatus::class, function (Container $c) {
            return new ToggleUserStatus(
                $c->getTyped(UserRepositoryInterface::class)
            );
        });

        $container->singleton(ToggleUserRole::class, function (Container $c) {
            return new ToggleUserRole(
                $c->getTyped(MySqlUserRepository::class)
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
        // Settings Repository
        $container->singleton(SettingsRepositoryInterface::class, function (Container $_c) {
            return new MySqlSettingsRepository();
        });

        $container->singleton(MySqlSettingsRepository::class, function (Container $c): SettingsRepositoryInterface {
            return $c->getTyped(SettingsRepositoryInterface::class);
        });

        // Backup Repository
        $container->singleton(BackupRepositoryInterface::class, function (Container $_c) {
            return new MySqlBackupRepository();
        });

        $container->singleton(MySqlBackupRepository::class, function (Container $c): BackupRepositoryInterface {
            return $c->getTyped(BackupRepositoryInterface::class);
        });

        // Statistics Repository (no interface needed, concrete only)
        $container->singleton(MySqlStatisticsRepository::class, function (Container $_c) {
            return new MySqlStatisticsRepository();
        });

        // Env Repository (no database needed, works standalone)
        $container->singleton(FileSystemEnvRepository::class, function (Container $_c) {
            return new FileSystemEnvRepository();
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
        // Session Cleaner
        $container->singleton(SessionCleaner::class, function (Container $_c) {
            return new SessionCleaner();
        });

        // TTS Service
        $container->singleton(TtsService::class, function (Container $c) {
            return new TtsService(
                $c->getTyped(\Lukaisu\Modules\Language\Application\LanguageFacade::class)
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Admin module
    }
}
