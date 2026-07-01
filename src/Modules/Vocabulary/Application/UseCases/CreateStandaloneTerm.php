<?php

/**
 * Create Standalone Term Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\UseCases;

use Lukaisu\Modules\Vocabulary\Application\Services\WordCrudService;
use Lukaisu\Modules\Vocabulary\Application\Services\ExpressionService;
use Lukaisu\Modules\Vocabulary\Application\Services\WordLinkingService;
use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermStatus;
use Lukaisu\Modules\Tags\Application\TagsFacade;
use Lukaisu\Shared\Infrastructure\Database\Maintenance;
use Lukaisu\Shared\Infrastructure\Utilities\StringUtils;

/**
 * Create a term outside of any text (the standalone "new term" form).
 *
 * Mirrors the retired TermEditController@createWord POST path: insert the word,
 * attach tags, then link it into existing texts' occurrences — as a multi-word
 * expression when the term spans several words, or as a single-word occurrence
 * link otherwise. Unlike the reader's `POST /terms/full` create (which derives
 * the word from a text position), this takes the language + text directly, so a
 * term can be created with no text context.
 */
class CreateStandaloneTerm
{
    private WordCrudService $crudService;
    private ExpressionService $expressionService;
    private WordLinkingService $linkingService;

    /**
     * Constructor.
     *
     * @param WordCrudService|null    $crudService       Word CRUD service
     * @param ExpressionService|null  $expressionService Multi-word expression service
     * @param WordLinkingService|null $linkingService    Single-word linking service
     */
    public function __construct(
        ?WordCrudService $crudService = null,
        ?ExpressionService $expressionService = null,
        ?WordLinkingService $linkingService = null
    ) {
        $this->crudService = $crudService ?? new WordCrudService();
        $this->expressionService = $expressionService ?? new ExpressionService();
        $this->linkingService = $linkingService ?? new WordLinkingService();
    }

    /**
     * Create the term and link it into existing texts.
     *
     * @param int         $langId      Language ID
     * @param string      $text        Term text (may span several words)
     * @param int         $status      Learning status (1-5, 98, 99)
     * @param string      $translation Translation ('' is stored as '*')
     * @param string      $romanization Romanization / reading
     * @param string      $sentence    Example sentence
     * @param string      $notes       Notes
     * @param string|null $lemma       Lemma / base form
     * @param list<string> $tags       Tag names
     *
     * @return array{success: bool, term?: array<string, mixed>, error?: string}
     */
    public function execute(
        int $langId,
        string $text,
        int $status,
        string $translation,
        string $romanization,
        string $sentence,
        string $notes,
        ?string $lemma,
        array $tags
    ): array {
        $text = trim($text);
        if ($langId <= 0 || $text === '') {
            return ['success' => false, 'error' => 'Language and text are required'];
        }
        if (!TermStatus::isValid($status)) {
            return ['success' => false, 'error' => 'Status must be 1-5, 98, or 99'];
        }

        $result = $this->crudService->create([
            'language_id' => $langId,
            'text' => $text,
            'status' => $status,
            'translation' => $translation,
            'romanization' => $romanization,
            'sentence' => $sentence,
            'notes' => $notes,
            'lemma' => $lemma,
        ]);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['message']];
        }

        $wid = $result['id'];
        $textlc = $result['textlc'];

        if (count($tags) > 0) {
            TagsFacade::saveWordTagsFromArray($wid, $tags);
        }

        // Populate word-count metadata, then link the new term into existing
        // texts: multi-word terms register as expressions, single words link
        // their existing occurrences.
        Maintenance::initWordCount();
        $len = $this->crudService->getWordCount($wid);
        if ($len > 1) {
            $this->expressionService->insertExpressions($textlc, $langId, $wid, $len, 0);
        } elseif ($len === 1) {
            $this->linkingService->linkToTextItems($wid, $langId, $textlc);
        }

        return [
            'success' => true,
            'term' => [
                'id' => $wid,
                'text' => $result['text'],
                'textLc' => $textlc,
                'hex' => StringUtils::toClassName($textlc),
                'translation' => ($translation === '' || $translation === '*') ? '' : $translation,
                'romanization' => $romanization,
                'sentence' => $sentence,
                'notes' => $notes,
                'lemma' => $lemma ?? '',
                'status' => $status,
                'tags' => $tags,
            ],
        ];
    }
}
