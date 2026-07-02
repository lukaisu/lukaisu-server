<?php

/**
 * Controller Service Provider
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

use Lukaisu\Controllers\ApiController;
// Note: AuthController moved to Modules/User as UserController - registered by UserServiceProvider
// Note: FeedsController moved to Modules/Feed as FeedController - registered by FeedServiceProvider
// Note: HomeController moved to Modules/Home - registered by HomeServiceProvider
// Note: LanguageController retired - language management served by the bundled client (LanguageApiHandler)
// Note: TestController moved to Modules/Review - registered by ReviewServiceProvider
use Lukaisu\Modules\Text\Http\TextController;
// Note: TextPrintController moved to Modules/Text - registered by TextServiceProvider
// Note: TranslationController moved to Modules/Dictionary - registered by DictionaryServiceProvider
// Note: WordController moved to Modules/Vocabulary as VocabularyController - registered by VocabularyServiceProvider
// Note: WordPressController moved to Modules/User - registered by UserServiceProvider
// Note: AuthService now primarily used via UserFacade in User module
// Note: HomeService moved to Modules/Home as HomeFacade
// Note: TestService now primarily used via ReviewFacade in Review module
// Note: TextPrintService now primarily used via TextPrintController in Text module
use Lukaisu\Modules\Text\Application\TextFacade;

// Note: TranslationService moved to Modules/Dictionary - registered by DictionaryServiceProvider
// Note: WordPressService moved to Modules/User - registered by UserServiceProvider

/**
 * Controller service provider that registers all controllers.
 *
 * Controllers are NOT registered as singletons - a fresh instance
 * is created for each request to avoid state issues.
 */
class ControllerServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Controllers are registered as factories (new instance each time)
        // This ensures clean state for each request
        // Dependencies are injected from the container

        // Controllers with service dependencies
        $container->bind(ApiController::class, function (Container $_c) {
            return new ApiController();
        });

        // Note: AuthController removed - UserController is now registered by UserServiceProvider

        // Note: LanguageController retired - language management is served by the
        // bundled client via the /api/v1/languages REST API (LanguageApiHandler)

        // Note: TestController removed - now registered by ReviewServiceProvider

        // Note: TextPrintController removed - now registered by TextServiceProvider

        // Note: TranslationController removed - now registered by DictionaryServiceProvider

        // Note: WordPressController removed - now registered by UserServiceProvider

        // Note: FeedsController moved to Modules/Feed as FeedController - registered by FeedServiceProvider

        // Note: HomeController moved to Modules/Home - registered by HomeServiceProvider

        $container->bind(TextController::class, function (Container $c) {
            return new TextController(
                $c->getTyped(TextFacade::class)
            );
        });

        // Note: WordController moved to Modules/Vocabulary as VocabularyController
        // Registered by VocabularyServiceProvider

        // NOTE: AdminController is now in Modules/Admin and registered via AdminServiceProvider
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for controllers
    }
}
