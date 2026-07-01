<?php

/**
 * Term Status Controller
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
use Lukaisu\Modules\Vocabulary\Application\VocabularyFacade;

/**
 * Controller for term status operations.
 *
 * Handles:
 * - PUT /vocabulary/term/{wid}/status - Update status
 */
class TermStatusController extends VocabularyBaseController
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
     * Update term status.
     *
     * Routes:
     * - PUT /vocabulary/term/{wid:int}/status (new RESTful route)
     * - PUT /vocabulary/term/status?wid=[id] (legacy route)
     *
     * Body: {"status": 1-5|98|99}
     *
     * @param int|null $wid Term ID (injected from route parameter)
     *
     * @return void
     */
    public function updateStatus(?int $wid = null): void
    {
        // Support both new route param injection and legacy query param
        $termId = $wid ?? InputValidator::getInt('wid', 0) ?? 0;
        $status = InputValidator::getInt('status', 0) ?? 0;

        if ($termId === 0 || $status === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Term ID and status required']);
            return;
        }

        $result = $this->facade->updateStatus($termId, $status);

        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    }
}
