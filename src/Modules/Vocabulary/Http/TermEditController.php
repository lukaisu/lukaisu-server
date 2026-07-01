<?php

/**
 * Term Edit Controller
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Http;

use Lukaisu\Shared\Infrastructure\Http\InputValidator;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use Lukaisu\Modules\Vocabulary\Application\VocabularyFacade;

/**
 * Controller for the remaining single-term edit endpoints.
 *
 * Handles:
 * - POST /word/inline-edit - Inline edit translation/romanization (AJAX)
 * - DELETE /words/{id} - Delete term
 *
 * The server-rendered create/edit forms (editWord / editTerm / createWord /
 * editWordById + the form_edit_* / form_new / *_result views) were retired under
 * the headless cut: the bundled client edits inline via /api/v1/terms, creates a
 * standalone term via the bundled new-term page (POST /api/v1/terms/standalone),
 * and creates/edits in-context via the reader's term modal.
 */
class TermEditController extends VocabularyBaseController
{
    /**
     * Vocabulary facade.
     */
    private VocabularyFacade $facade;

    /**
     * Constructor.
     *
     * @param VocabularyFacade|null $facade Vocabulary facade
     */
    public function __construct(
        ?VocabularyFacade $facade = null
    ) {
        parent::__construct();
        $this->facade = $facade ?? new VocabularyFacade();
    }

    /**
     * Inline edit of a term's translation or romanization (AJAX).
     *
     * POST parameters:
     * - id: string - Field identifier (e.g., "trans123" or "roman123" where 123 is word ID)
     * - value: string - New value for the field
     *
     * @param array<string, string> $params Route parameters
     *
     * @return void
     */
    public function inlineEdit(array $params): void
    {
        $value = InputValidator::getStringFromPost('value');
        $id = InputValidator::getStringFromPost('id');

        if (substr($id, 0, 5) === 'trans') {
            $wordId = (int) substr($id, 5);
            $term = $this->facade->getTerm($wordId);
            if ($term === null) {
                echo 'ERROR - term not found!';
                return;
            }
            $this->facade->updateTerm($wordId, null, $value ?: '*', null, null, null);
            $displayValue = $value ?: '*';
            echo htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8');
            return;
        }

        if (substr($id, 0, 5) === 'roman') {
            $wordId = (int) substr($id, 5);
            $term = $this->facade->getTerm($wordId);
            if ($term === null) {
                echo 'ERROR - term not found!';
                return;
            }
            $this->facade->updateTerm($wordId, null, null, null, null, $value);
            echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            return;
        }

        echo 'ERROR - please refresh page!';
    }

    /**
     * Delete word.
     *
     * Route: DELETE /words/{id}
     *
     * @param int $id Word ID from route parameter
     *
     * @return RedirectResponse Redirect to words list
     */
    public function deleteWord(int $id): RedirectResponse
    {
        $this->facade->deleteTerm($id);

        return new RedirectResponse('/words');
    }
}
