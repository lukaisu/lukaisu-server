<?php

/**
 * Text Controller (Facade)
 *
 * Thin facade delegating to TextCrudController and ArchivedTextController.
 * Maintained for backward compatibility
 * with existing route registrations.
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
 * Facade controller delegating to specialized sub-controllers.
 *
 * The server-rendered create/edit forms (new/editSingle/archivedEdit +
 * edit_form.php/archived_form.php) were dropped under the headless cut: the
 * bundled client owns those screens and creates/updates via /api/v1/texts.
 * What remains here are the list + delete/archive data routes.
 */
class TextController extends BaseController
{
    private TextCrudController $crudController;
    private ArchivedTextController $archivedController;

    public function __construct(?TextFacade $textService = null)
    {
        parent::__construct();
        $textService = $textService ?? new TextFacade();

        $this->crudController = new TextCrudController($textService);
        $this->archivedController = new ArchivedTextController($textService);
    }

    // =========================================================================
    // CRUD Delegation
    // =========================================================================

    public function delete(int $id): RedirectResponse
    {
        return $this->crudController->delete($id);
    }

    public function archive(int $id): RedirectResponse
    {
        return $this->crudController->archive($id);
    }

    public function unarchive(int $id): RedirectResponse
    {
        return $this->crudController->unarchive($id);
    }

    // =========================================================================
    // Archived Text Delegation
    // =========================================================================

    public function deleteArchived(int $id): RedirectResponse
    {
        return $this->archivedController->deleteArchived($id);
    }
}
