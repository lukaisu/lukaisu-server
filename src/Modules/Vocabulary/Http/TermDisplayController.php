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
use Lukaisu\Modules\Vocabulary\Application\VocabularyFacade;
use Lukaisu\Modules\Vocabulary\Application\UseCases\FindSimilarTerms;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Modules\Tags\Application\TagsFacade;
use Lukaisu\Shared\UI\Helpers\PageLayoutHelper;

/**
 * Controller for viewing terms and hover interactions.
 *
 * Handles:
 * - GET /word/{wid} - Show word details
 * - /word/show - Show word details (legacy)
 * - /vocabulary/similar-terms - Get similar terms
 */
class TermDisplayController extends VocabularyBaseController
{
    /**
     * Vocabulary facade.
     */
    private VocabularyFacade $facade;

    /**
     * Use cases.
     */
    private FindSimilarTerms $findSimilarTerms;

    /**
     * Services.
     */
    private LanguageFacade $languageFacade;

    /**
     * Constructor.
     *
     * @param VocabularyFacade|null $facade           Vocabulary facade
     * @param FindSimilarTerms|null $findSimilarTerms Find similar terms use case
     * @param LanguageFacade|null   $languageFacade   Language facade
     */
    public function __construct(
        ?VocabularyFacade $facade = null,
        ?FindSimilarTerms $findSimilarTerms = null,
        ?LanguageFacade $languageFacade = null
    ) {
        parent::__construct();
        $this->facade = $facade ?? new VocabularyFacade();
        $this->findSimilarTerms = $findSimilarTerms ?? new FindSimilarTerms();
        $this->languageFacade = $languageFacade ?? new LanguageFacade();
    }

    /**
     * Show word details.
     *
     * Routes:
     * - GET /word/{wid:int} (new RESTful route)
     * - GET /word/show?wid=[wordid] (legacy route)
     *
     * Optional query parameter: ann=[annotation]
     *
     * @param int|null $wid Word ID (injected from route parameter)
     *
     * @return void
     */
    public function showWord(?int $wid = null): void
    {
        PageLayoutHelper::renderPageStartNobody('Term');

        // Support both new route param injection and legacy query param
        if ($wid === null) {
            $widParam = InputValidator::getString('wid');
            $wid = $widParam !== '' ? (int) $widParam : null;
        }
        $ann = InputValidator::getString('ann');

        if ($wid === null) {
            echo '<p>Word ID is required</p>';
            PageLayoutHelper::renderPageEnd();
            return;
        }

        $term = $this->facade->getTerm($wid);
        if ($term === null) {
            echo '<p>Word not found</p>';
            PageLayoutHelper::renderPageEnd();
            return;
        }

        $tags = TagsFacade::getWordTagList($wid, false);
        $scrdir = $this->languageFacade->getScriptDirectionTag($term->languageId()->toInt());

        // Convert Term entity to array for view compatibility
        $word = [
            'text' => $term->text(),
            'translation' => $term->translation(),
            'sentence' => $term->sentence(),
            'romanization' => $term->romanization(),
            'notes' => $term->notes(),
            'status' => $term->status()->toInt(),
            'langId' => $term->languageId()->toInt(),
        ];

        $this->render('show', [
            'word' => $word,
            'tags' => $tags,
            'scrdir' => $scrdir,
            'ann' => $ann,
        ]);

        PageLayoutHelper::renderPageEnd();
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
