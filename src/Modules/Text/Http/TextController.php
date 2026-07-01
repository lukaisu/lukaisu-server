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
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;

/**
 * Facade controller delegating to specialized sub-controllers.
 */
class TextController extends BaseController
{
    private TextCrudController $crudController;
    private ArchivedTextController $archivedController;

    public function __construct(
        ?TextFacade $textService = null,
        ?LanguageFacade $languageService = null
    ) {
        parent::__construct();
        $textService = $textService ?? new TextFacade();
        $languageService = $languageService ?? new LanguageFacade();

        $this->crudController = new TextCrudController($textService, $languageService);
        $this->archivedController = new ArchivedTextController($textService, $languageService);
    }

    // =========================================================================
    // CRUD Delegation
    // =========================================================================

    /** @psalm-suppress UnusedVariable */
    public function new(array $params): ?RedirectResponse
    {
        return $this->crudController->new($params);
    }

    public function editSingle(int $id): ?RedirectResponse
    {
        return $this->crudController->editSingle($id);
    }

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

    /** @psalm-suppress UnusedVariable */
    public function edit(array $params): ?RedirectResponse
    {
        return $this->crudController->edit($params);
    }

    // =========================================================================
    // Archived Text Delegation
    // =========================================================================

    /** @psalm-suppress UnusedVariable */
    public function archived(array $params): ?RedirectResponse
    {
        return $this->archivedController->archived($params);
    }

    public function archivedEdit(int $id): ?RedirectResponse
    {
        return $this->archivedController->archivedEdit($id);
    }

    public function deleteArchived(int $id): RedirectResponse
    {
        return $this->archivedController->deleteArchived($id);
    }
}
