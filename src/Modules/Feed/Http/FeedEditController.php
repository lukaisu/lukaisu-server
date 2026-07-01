<?php

declare(strict_types=1);

namespace Lukaisu\Modules\Feed\Http;

use Lukaisu\Modules\Feed\Application\FeedFacade;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Shared\Infrastructure\Http\FlashMessageService;
use Lukaisu\Shared\Infrastructure\Http\InputValidator;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;

/**
 * Controller for feed CRUD operations.
 *
 * The GET new/edit *forms* are the bundled Svelte FeedFormPage island (their
 * routes 302 into the bundle); this controller only handles the island's native
 * form POST coexistence (create/update), the JSON config data routes that
 * bootstrap the island, and feed deletion.
 */
class FeedEditController
{
    use FeedFlashTrait;

    private string $viewPath;
    private FeedFacade $feedFacade;
    private LanguageFacade $languageFacade;
    private FlashMessageService $flashService;

    public function __construct(
        FeedFacade $feedFacade,
        LanguageFacade $languageFacade,
        ?FlashMessageService $flashService = null
    ) {
        $this->viewPath = __DIR__ . '/../Views/';
        $this->feedFacade = $feedFacade;
        $this->languageFacade = $languageFacade;
        $this->flashService = $flashService ?? new FlashMessageService();
    }

    /**
     * Create a feed from the bundled form's native POST.
     *
     * The GET /feeds/new page 302s into the Svelte FeedFormPage island, so the
     * only request that reaches this handler is the island's native form submit
     * (save_feed). On success it redirects to the feed's edit page.
     *
     * Route: POST /feeds/new
     *
     * @param array<string, string> $params Route parameters (unused).
     *
     * @return void
     */
    public function newFeed(array $params): void
    {
        unset($params);

        if (InputValidator::has('save_feed')) {
            $data = [
                'language_id' => InputValidator::getString('language_id'),
                'name' => InputValidator::getString('name'),
                'source_uri' => InputValidator::getString('source_uri'),
                'article_section_tags' => InputValidator::getString('article_section_tags'),
                'filter_tags' => InputValidator::getString('filter_tags'),
                'options' => rtrim(InputValidator::getString('options'), ','),
            ];

            $feedId = $this->feedFacade->createFeed($data);
            $this->flashService->success(__('feed.flash.created'));
            $this->redirect(url('/feeds/' . $feedId . '/edit'));
            return;
        }

        // Defensive: a request with no form payload → back to the manage list.
        $this->redirect(url('/feeds/manage'));
    }

    /**
     * Update a feed from the bundled form's native POST.
     *
     * The GET /feeds/{id}/edit page 302s into the Svelte FeedFormPage island, so
     * the only request that reaches this handler is the island's native form
     * submit (update_feed).
     *
     * Route: POST /feeds/{id}/edit
     *
     * @param int $id Feed ID from route parameter
     *
     * @return void
     */
    public function editFeed(int $id): void
    {
        $feed = $this->feedFacade->getFeedById($id);

        if ($feed === null) {
            $this->flashService->error(__('feed.flash.not_found'));
            $this->redirect(url('/feeds/manage'));
            return;
        }

        if (InputValidator::has('update_feed')) {
            $data = [
                'language_id' => InputValidator::getString('language_id'),
                'name' => InputValidator::getString('name'),
                'source_uri' => InputValidator::getString('source_uri'),
                'article_section_tags' => InputValidator::getString('article_section_tags'),
                'filter_tags' => InputValidator::getString('filter_tags'),
                'options' => rtrim(InputValidator::getString('options'), ','),
            ];

            $this->feedFacade->updateFeed($id, $data);
            $this->flashService->success(__('feed.flash.updated'));
            $this->redirect(url('/feeds/manage'));
            return;
        }

        // Defensive: a request with no form payload → back to the manage list.
        $this->redirect(url('/feeds/manage'));
    }

    /**
     * Delete a feed.
     *
     * Route: DELETE /feeds/{id}
     *
     * @param int $id Feed ID from route parameter
     *
     * @return void
     */
    public function deleteFeed(int $id): void
    {
        $result = $this->feedFacade->deleteFeeds((string)$id);

        if ($result['feeds'] > 0) {
            $this->flashService->success(__('feed.flash.deleted'));
        } else {
            $this->flashService->error(__('feed.flash.delete_failed'));
        }

        $this->redirect(url('/feeds/manage'));
    }

    /**
     * Bootstrap config for the bundled feed *create* form (Svelte FeedFormPage).
     *
     * The GET /feeds/new page 302s into the bundle; the island cannot list the
     * user's languages itself, so it fetches them here on mount. Mirrors the JSON
     * blob the retired `new.php` view used to inline.
     *
     * Route: GET /feeds/new/config
     *
     * @param array<string, string> $params Route/query parameters (unused).
     *
     * @return JsonResponse
     */
    public function configNew(array $params): JsonResponse
    {
        unset($params);

        $languages = $this->languageFacade->getLanguagesForSelect();
        $currentLanguageId = (int) Settings::get('currentlanguage');
        // A user who just created their first language hasn't toggled the navbar
        // dropdown, so 'currentlanguage' is unset — fall back to the first language;
        // without this the create form posts language_id=0.
        if ($currentLanguageId === 0 && !empty($languages)) {
            /** @var array{id: int|string} $first */
            $first = $languages[0];
            $currentLanguageId = (int) $first['id'];
        }

        return JsonResponse::success([
            'languages' => $languages,
            'currentLang' => $currentLanguageId,
            'feed' => null,
        ]);
    }

    /**
     * Bootstrap config for the bundled feed *edit* form (Svelte FeedFormPage).
     *
     * The GET /feeds/{id}/edit page 302s into the bundle; the island fetches the
     * language list plus the feed record to prefill here on mount. The feed is
     * returned in the same shape as GET /api/v1/feeds/{id} (parsed `options` +
     * raw `optionsString`), so the island seeds its option toggles consistently.
     *
     * Route: GET /feeds/{id}/edit/config
     *
     * @param int $id Feed ID from route parameter.
     *
     * @return JsonResponse
     */
    public function configEdit(int $id): JsonResponse
    {
        $crud = new FeedCrudApiHandler($this->feedFacade);
        $feed = $crud->getFeed($id);
        if (isset($feed['error'])) {
            return JsonResponse::notFound((string) $feed['error']);
        }

        return JsonResponse::success([
            'languages' => $this->languageFacade->getLanguagesForSelect(),
            'currentLang' => (int) ($feed['langId'] ?? 0),
            'feed' => $feed,
        ]);
    }

    /**
     * Send a redirect response.
     *
     * Extracted to allow tests to override and prevent exit().
     *
     * @param string $url Target URL
     *
     * @return void
     *
     * @codeCoverageIgnore
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
