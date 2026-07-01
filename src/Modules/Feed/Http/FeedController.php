<?php

/**
 * Feed Controller (Facade)
 *
 * Thin facade delegating to FeedEditController for the surviving feed
 * routes (the create/edit form POST coexistence + their JSON config data
 * routes + delete). Maintained for backward compatibility with existing
 * route registrations.
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
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Shared\Infrastructure\Http\FlashMessageService;

/**
 * Facade controller delegating to the FeedEditController sub-controller.
 */
class FeedController
{
    private FeedFacade $feedFacade;
    private FeedEditController $editController;

    public function __construct(
        FeedFacade $feedFacade,
        LanguageFacade $languageFacade,
        ?FlashMessageService $flashService = null
    ) {
        $this->feedFacade = $feedFacade;
        $this->editController = new FeedEditController(
            $feedFacade,
            $languageFacade,
            $flashService
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
}
