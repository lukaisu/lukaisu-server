<?php

/**
 * Text CRUD Controller - Text creation, editing, and list management
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
 * Controller for text CRUD operations and list management.
 *
 * Handles:
 * - New text form and save
 * - Edit text form and save
 * - Delete/archive/unarchive single texts
 * - Text list with bulk actions
 */
class TextCrudController extends BaseController
{
    private TextFacade $textService;

    public function __construct(?TextFacade $textService = null)
    {
        parent::__construct();
        $this->textService = $textService ?? new TextFacade();
    }

    /**
     * Delete a text.
     *
     * @param int $id Text ID from route parameter
     *
     * @return RedirectResponse Redirect to texts list
     */
    public function delete(int $id): RedirectResponse
    {
        $this->textService->deleteText($id);
        return $this->redirect('/texts');
    }

    /**
     * Archive a text.
     *
     * @param int $id Text ID from route parameter
     *
     * @return RedirectResponse Redirect to texts list
     */
    public function archive(int $id): RedirectResponse
    {
        $this->textService->archiveText($id);
        return $this->redirect('/texts');
    }

    /**
     * Unarchive a text.
     *
     * @param int $id Text ID from route parameter
     *
     * @return RedirectResponse Redirect to archived texts list
     */
    public function unarchive(int $id): RedirectResponse
    {
        $this->textService->unarchiveText($id);
        return $this->redirect('/text/archived');
    }
}
