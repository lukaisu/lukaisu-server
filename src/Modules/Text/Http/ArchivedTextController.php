<?php

/**
 * Archived Text Controller - Archived text management
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Modules\Tags\Application\TagsFacade;
use Lukaisu\Shared\UI\Helpers\PageLayoutHelper;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;

/**
 * Controller for archived text management.
 *
 * The server-rendered archived edit form (archivedEdit + archived_form.php) was
 * dropped under the headless cut: the bundled client owns that screen and
 * updates via /api/v1/texts. What remains are the archived list's POST
 * bulk-action + single delete/unarchive data paths.
 */
class ArchivedTextController extends BaseController
{
    private TextFacade $textService;

    public function __construct(?TextFacade $textService = null)
    {
        parent::__construct();
        $this->textService = $textService ?? new TextFacade();
    }

    /**
     * Archived texts management (bulk actions + single delete/unarchive).
     *
     * @param array $params Route parameters
     *
     * @return RedirectResponse|null Redirect response or null if rendered
     *
     * @psalm-suppress UnusedVariable $message/$params are vestigial now that the
     *   archived list view is served by the bundled client.
     */
    public function archived(array $params): ?RedirectResponse
    {
        $markAction = $this->param('markaction');
        $noPagestart = ($markAction == 'deltag');
        if (!$noPagestart) {
            PageLayoutHelper::renderPageStart('Archived Texts', true);
        }

        $message = '';

        if ($markAction !== '') {
            $result = $this->handleArchivedMarkAction(
                $markAction,
                $this->paramArray('marked'),
                $this->param('data')
            );
            if ($result instanceof RedirectResponse) {
                return $result;
            }
            $message = $result;
        }

        $delId = $this->paramInt('del');
        $unarchId = $this->paramInt('unarch');
        if ($delId !== null) {
            $message = $this->textService->deleteArchivedText($delId);
        } elseif ($unarchId !== null) {
            $result = $this->textService->unarchiveText($unarchId);
            if ($result['success'] ?? false) {
                $message = "Text unarchived: {$result['sentences']} sentences, {$result['textItems']} text items";
            } else {
                $message = $result['error'] ?? 'Failed to unarchive text';
            }
        }
        // Cut-over: the archived texts list + edit form are served by the
        // bundled client. GET /text/archived and /text/archived/{id}/edit 302
        // to the bundle (see routes.php); this handler keeps only the POST
        // bulk-action + single delete/unarchive data paths. The PHP views
        // (archived_list.php, archived_form.php) were removed.

        PageLayoutHelper::renderPageEnd();

        return null;
    }

    /**
     * Delete an archived text.
     *
     * @param int $id Archived text ID from route parameter
     *
     * @return RedirectResponse Redirect to archived texts list
     */
    public function deleteArchived(int $id): RedirectResponse
    {
        $this->textService->deleteArchivedText($id);
        return $this->redirect('/text/archived');
    }

    /**
     * Handle mark actions for archived texts.
     *
     * @param string $markAction Action to perform
     * @param array  $marked     Array of marked text IDs
     * @param string $actionData Additional data for the action
     *
     * @return string|RedirectResponse Result message or redirect
     */
    public function handleArchivedMarkAction(
        string $markAction,
        array $marked,
        string $actionData
    ): string|RedirectResponse {
        $message = "Multiple Actions: 0";

        if (count($marked) === 0) {
            return $message;
        }

        switch ($markAction) {
            case 'del':
                $result = $this->textService->deleteArchivedTexts($marked);
                $message = "Archived Texts deleted: {$result['count']}";
                break;

            case 'addtag':
                $result = TagsFacade::addTagToArchivedTexts($actionData, $marked);
                $message = $result['error'] ?? "Tag added in {$result['count']} Texts";
                break;

            case 'deltag':
                TagsFacade::removeTagFromArchivedTexts($actionData, $marked);
                return $this->redirect('/text/archived');

            case 'unarch':
                $result = $this->textService->unarchiveTexts($marked);
                $message = "Unarchived Text(s): {$result['count']}";
                break;
        }

        return $message;
    }
}
