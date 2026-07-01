<?php

/**
 * Term Display Controller
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
use Lukaisu\Modules\Vocabulary\Application\UseCases\FindSimilarTerms;

/**
 * Controller for term lookup helpers.
 *
 * Handles:
 * - /vocabulary/similar-terms - Get similar terms (AJAX)
 *
 * The read-only term detail page (showWord + show.php) was dropped under the
 * headless cut; term details are served to the bundled client by
 * GET /api/v1/terms/{id}/details.
 */
class TermDisplayController extends VocabularyBaseController
{
    /**
     * Use cases.
     */
    private FindSimilarTerms $findSimilarTerms;

    /**
     * Constructor.
     *
     * @param FindSimilarTerms|null $findSimilarTerms Find similar terms use case
     */
    public function __construct(
        ?FindSimilarTerms $findSimilarTerms = null
    ) {
        parent::__construct();
        $this->findSimilarTerms = $findSimilarTerms ?? new FindSimilarTerms();
    }

    /**
     * Get similar terms for a given term.
     *
     * @param array<string, string> $params Route parameters
     *
     * @return void
     */
    public function similarTerms(array $params): void
    {
        $langId = InputValidator::getInt('lgid', 0) ?? 0;
        $term = InputValidator::getString('term');

        header('Content-Type: text/html; charset=utf-8');
        // Safe: getFormattedTerms() returns pre-escaped HTML
        echo $this->findSimilarTerms->getFormattedTerms($langId, $term);
    }
}
