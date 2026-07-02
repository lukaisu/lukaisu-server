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
}
