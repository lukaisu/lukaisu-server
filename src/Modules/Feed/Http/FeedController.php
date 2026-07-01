<?php

/**
 * Feed Controller (Facade)
 *
 * Thin facade delegating to FeedIndexController, FeedEditController,
 * and FeedLoadController. Maintained for backward compatibility with
 * existing route registrations.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Feed\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Feed\Http;

use Lukaisu\Modules\Feed\Application\FeedFacade;
use Lukaisu\Modules\Feed\Infrastructure\FeedWizardSessionManager;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Shared\Infrastructure\Http\FlashMessageService;

/**
 * Facade controller delegating to specialized sub-controllers.
 */
class FeedController
{
    private FeedFacade $feedFacade;
    private FeedIndexController $indexController;
    private FeedEditController $editController;
    private FeedLoadController $loadController;

    public function __construct(
        FeedFacade $feedFacade,
        LanguageFacade $languageFacade,
        ?FeedWizardSessionManager $wizardSession = null,
        ?FlashMessageService $flashService = null
    ) {
        $this->feedFacade = $feedFacade;
        $this->indexController = new FeedIndexController(
            $feedFacade,
            $languageFacade,
            $flashService
        );
        $this->editController = new FeedEditController(
            $feedFacade,
            $languageFacade,
            $wizardSession,
            $flashService
        );
        $this->loadController = new FeedLoadController(
            $feedFacade,
            $languageFacade
        );
    }

    /**
     * Get the FeedFacade instance.
     *
     * @return FeedFacade
     */
    public function getFacade(): FeedFacade
    {
        return $this->feedFacade;
    }

    // =========================================================================
    // Delegated Route Handlers
    // =========================================================================

    /** @param array<string, string> $params */
    public function index(array $params): void
    {
        $this->indexController->index($params);
    }

    /** @param array<string, string> $params */
    public function edit(array $params): void
    {
        $this->editController->edit($params);
    }

    /** @param array<string, string> $params */
    public function newFeed(array $params): void
    {
        $this->editController->newFeed($params);
    }

    public function editFeed(int $id): void
    {
        $this->editController->editFeed($id);
    }

    /**
     * Bootstrap config for the bundled feed create form.
     *
     * Route: GET /feeds/new/config
     *
     * @param array<string, string> $params Route/query parameters.
     *
     * @return \Lukaisu\Shared\Infrastructure\Http\JsonResponse
     */
    public function configNew(array $params): \Lukaisu\Shared\Infrastructure\Http\JsonResponse
    {
        return $this->editController->configNew($params);
    }

    /**
     * Bootstrap config for the bundled feed edit form.
     *
     * Route: GET /feeds/{id}/edit/config
     *
     * @param int $id Feed ID from route parameter.
     *
     * @return \Lukaisu\Shared\Infrastructure\Http\JsonResponse
     */
    public function configEdit(int $id): \Lukaisu\Shared\Infrastructure\Http\JsonResponse
    {
        return $this->editController->configEdit($id);
    }

    public function deleteFeed(int $id): void
    {
        $this->editController->deleteFeed($id);
    }

    public function loadFeedRoute(int $id): void
    {
        $this->loadController->loadFeedRoute($id);
    }

    /** @param array<string, string> $params */
    public function multiLoad(array $params): void
    {
        $this->loadController->multiLoad($params);
    }

    /**
     * Render feed load interface (used by renderFeedLoadInterfaceModern delegation).
     */
    public function renderFeedLoadInterface(
        int $currentFeed,
        bool $checkAutoupdate,
        string $redirectUrl
    ): void {
        $this->loadController->renderFeedLoadInterface($currentFeed, $checkAutoupdate, $redirectUrl);
    }
}
