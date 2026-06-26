<?php

/**
 * Term Tag Controller
 *
 * Controller for managing term tags in the Tags module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Tags\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Tags\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Modules\Tags\Application\TagsFacade;

/**
 * Controller for managing term tags (tags applied to vocabulary words).
 *
 * The term-tag list is served by the bundled client (GET /tags redirects to
 * /app/tags.html); this controller only renders the server-side create/edit
 * forms and handles deletes.
 */
class TermTagController extends BaseController
{
    protected string $pageTitle = 'Term Tags';
    protected bool $showMenu = true;

    private TagsFacade $facade;

    /**
     * Constructor.
     *
     * @param TagsFacade|null $facade Tags facade
     */
    public function __construct(?TagsFacade $facade = null)
    {
        parent::__construct();
        $this->facade = $facade ?? TagsFacade::forTermTags();
    }

    /**
     * Show new term tag form.
     *
     * Route: GET/POST /tags/new
     *
     * @param array $params Route parameters
     *
     * @return void
     */
    public function new(array $params): void
    {
        $this->render($this->pageTitle, $this->showMenu);

        // Handle form submission
        if ($this->param('op') === 'Save') {
            $text = $this->param('text', '');
            $comment = $this->param('comment', '');
            $result = $this->facade->create($text, $comment);
            $message = $result['success']
                ? __('tags.flash.saved')
                : __('tags.flash.error_prefix', [
                    'message' => $result['error'] ?? __('tags.flash.unknown_error'),
                ]);
            $this->message($message, false);
        }

        $this->renderCreateForm();
        $this->endRender();
    }

    /**
     * Edit term tag form.
     *
     * Route: GET/POST /tags/{id}/edit
     *
     * @param int $id Tag ID from route parameter
     *
     * @return void
     */
    public function edit(int $id): void
    {
        $this->render($this->pageTitle, $this->showMenu);

        // Handle form submission
        if ($this->param('op') === 'Change') {
            $text = $this->param('text', '');
            $comment = $this->param('comment', '');
            $result = $this->facade->update($id, $text, $comment);
            if ($result['success']) {
                // Redirect to list on success
                header('Location: ' . url('/tags'));
                exit;
            }
            $this->message(
                __('tags.flash.error_prefix', [
                    'message' => $result['error'] ?? __('tags.flash.unknown_error'),
                ]),
                false
            );
        }

        $this->renderEditForm($id);
        $this->endRender();
    }

    /**
     * Delete a term tag.
     *
     * Route: DELETE /tags/{id}
     *
     * @param int $id Tag ID from route parameter
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $result = $this->facade->delete($id);

        if ($result['success']) {
            header('Location: ' . url('/tags') . '?message=' . urlencode(__('tags.flash.deleted')));
        } else {
            header('Location: ' . url('/tags') . '?error=' . urlencode(__('tags.flash.delete_failed')));
        }
        exit;
    }

    /**
     * Render the create form.
     *
     * @psalm-suppress UnusedVariable Variables are used by included view
     *
     * @return void
     */
    protected function renderCreateForm(): void
    {
        $mode = 'new';
        $tag = null;
        $service = $this->facade;
        $formFieldPrefix = 'Tg';

        include __DIR__ . '/../Views/tag_form.php';
    }

    /**
     * Render the edit form.
     *
     * @param int $id Tag ID
     *
     * @psalm-suppress UnusedVariable Variables are used by included view
     *
     * @return void
     */
    protected function renderEditForm(int $id): void
    {
        $tag = $this->facade->getById($id);

        if ($tag === null) {
            $this->message(__('tags.flash.not_found'), false);
            return;
        }

        $mode = 'edit';
        $service = $this->facade;
        $formFieldPrefix = 'Tg';

        include __DIR__ . '/../Views/tag_form.php';
    }
}
