<?php

/**
 * Text API Handler
 *
 * Thin facade delegating to focused sub-handlers:
 * - TextPositionApiHandler: position, audio, display mode, bulk status
 * - TextAnnotationApiHandler: annotation CRUD, print items, edit term form
 * - TextTermApiHandler: words, translations, scoring, text listing
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

use Lukaisu\Modules\Text\Application\Services\DifficultyEstimationService;
use Lukaisu\Modules\Text\Application\Services\GdlImportService;
use Lukaisu\Modules\Text\Application\Services\GutenbergSuggestionService;
use Lukaisu\Modules\Book\Application\BookFacade;
use Lukaisu\Modules\Tags\Application\TagsFacade;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Shared\Infrastructure\Database\TextParsing;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Http\GdlClient;
use Lukaisu\Modules\Vocabulary\Application\Services\WordDiscoveryService;
use Lukaisu\Shared\Http\ApiRoutableInterface;
use Lukaisu\Shared\Http\ApiRoutableTrait;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Http\GutenbergClient;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Api\V1\Response;

/**
 * Handler for text-related API operations.
 *
 * Delegates to TextPositionApiHandler, TextAnnotationApiHandler,
 * and TextTermApiHandler for actual logic.
 */
class TextApiHandler implements ApiRoutableInterface
{
    use ApiRoutableTrait;

    private TextPositionApiHandler $positionHandler;
    private TextAnnotationApiHandler $annotationHandler;
    private TextTermApiHandler $termHandler;

    public function __construct(?WordDiscoveryService $discoveryService = null)
    {
        $this->positionHandler = new TextPositionApiHandler($discoveryService);
        $this->annotationHandler = new TextAnnotationApiHandler();
        $this->termHandler = new TextTermApiHandler();
    }

    // =========================================================================
    // Position & Display Mode (delegates to TextPositionApiHandler)
    // =========================================================================

    public function saveTextPosition(int $textid, int $position): void
    {
        $this->positionHandler->saveTextPosition($textid, $position);
    }

    public function saveAudioPosition(int $textid, float $audioposition): void
    {
        $this->positionHandler->saveAudioPosition($textid, $audioposition);
    }

    public function formatSetTextPosition(int $textId, int $position): array
    {
        return $this->positionHandler->formatSetTextPosition($textId, $position);
    }

    public function formatSetAudioPosition(int $textId, float $position): array
    {
        return $this->positionHandler->formatSetAudioPosition($textId, $position);
    }

    public function setDisplayMode(int $textId, ?int $annotations, ?bool $romanization, ?bool $translation): array
    {
        return $this->positionHandler->setDisplayMode($textId, $annotations, $romanization, $translation);
    }

    public function formatSetDisplayMode(int $textId, array $params): array
    {
        return $this->positionHandler->formatSetDisplayMode($textId, $params);
    }

    public function markAllWellKnown(int $textId): array
    {
        return $this->positionHandler->markAllWellKnown($textId);
    }

    public function markAllIgnored(int $textId): array
    {
        return $this->positionHandler->markAllIgnored($textId);
    }

    public function formatMarkAllWellKnown(int $textId): array
    {
        return $this->positionHandler->formatMarkAllWellKnown($textId);
    }

    public function formatMarkAllIgnored(int $textId): array
    {
        return $this->positionHandler->formatMarkAllIgnored($textId);
    }

    // =========================================================================
    // Annotation & Print (delegates to TextAnnotationApiHandler)
    // =========================================================================

    public function saveImprTextData(int $textid, int $line, string $val): array
    {
        return $this->annotationHandler->saveImprTextData($textid, $line, $val);
    }

    public function saveImprText(int $textid, string $elem, object $data): array
    {
        return $this->annotationHandler->saveImprText($textid, $elem, $data);
    }

    public function formatSetAnnotation(int $textId, string $elem, string $data): array
    {
        return $this->annotationHandler->formatSetAnnotation($textId, $elem, $data);
    }

    public function getPrintItems(int $textId): array
    {
        return $this->annotationHandler->getPrintItems($textId);
    }

    public function formatGetPrintItems(int $textId): array
    {
        return $this->annotationHandler->formatGetPrintItems($textId);
    }

    public function getAnnotation(int $textId): array
    {
        return $this->annotationHandler->getAnnotation($textId);
    }

    public function formatGetAnnotation(int $textId): array
    {
        return $this->annotationHandler->formatGetAnnotation($textId);
    }

    public function makeTrans(int $i, ?int $wid, string $trans, string $word, int $lang): string
    {
        return $this->annotationHandler->makeTrans($i, $wid, $trans, $word, $lang);
    }

    public function editTermForm(int $textid): string
    {
        return $this->annotationHandler->editTermForm($textid);
    }

    public function formatEditTermForm(int $textId): array
    {
        return $this->annotationHandler->formatEditTermForm($textId);
    }

    // =========================================================================
    // Terms & Scoring (delegates to TextTermApiHandler)
    // =========================================================================

    public function getWords(int $textId): array
    {
        return $this->termHandler->getWords($textId);
    }

    public function formatGetWords(int $textId): array
    {
        return $this->termHandler->formatGetWords($textId);
    }

    public function formatTextsByLanguage(int $langId, array $params): array
    {
        return $this->termHandler->formatTextsByLanguage($langId, $params);
    }

    public function formatArchivedTextsByLanguage(int $langId, array $params): array
    {
        return $this->termHandler->formatArchivedTextsByLanguage($langId, $params);
    }

    public function getTranslations(int $wordId): array
    {
        return $this->termHandler->getTranslations($wordId);
    }

    public function getTermTranslations(string $wordlc, int $textid): array
    {
        return $this->termHandler->getTermTranslations($wordlc, $textid);
    }

    public function formatTermTranslations(string $termLc, int $textId): array
    {
        return $this->termHandler->formatTermTranslations($termLc, $textId);
    }

    public function formatGetTextScore(int $textId): array
    {
        return $this->termHandler->formatGetTextScore($textId);
    }

    public function formatGetTextScores(array $textIds): array
    {
        return $this->termHandler->formatGetTextScores($textIds);
    }

    public function formatGetRecommendedTexts(int $languageId, array $params): array
    {
        return $this->termHandler->formatGetRecommendedTexts($languageId, $params);
    }

    // =========================================================================
    // Routing Methods (ApiRoutableInterface)
    // =========================================================================

    public function routeGet(array $fragments, array $params): JsonResponse
    {
        $frag1 = $this->frag($fragments, 1);
        $frag2 = $this->frag($fragments, 2);

        if ($frag1 === 'gutenberg-suggestions') {
            return $this->handleGutenbergSuggestions($params);
        } elseif ($frag1 === 'library-search') {
            return $this->handleLibrarySearch($params);
        } elseif ($frag1 === 'library-preview') {
            return $this->handleLibraryPreview($params);
        } elseif ($frag1 === 'gdl-search') {
            return $this->handleGdlSearch($params);
        } elseif ($frag1 === 'reader-level') {
            return $this->handleReaderLevel($params);
        } elseif ($frag1 === 'scoring') {
            if ($frag2 === 'recommended') {
                $langId = (int) ($params['language_id'] ?? 0);
                if ($langId <= 0) {
                    return Response::error('language_id is required', 400);
                }
                return Response::success($this->formatGetRecommendedTexts($langId, $params));
            }
            $textId = isset($params['text_id']) ? (int) $params['text_id'] : 0;
            $textIds = (string) ($params['text_ids'] ?? '');

            if ($textId > 0) {
                return Response::success($this->formatGetTextScore($textId));
            } elseif ($textIds !== '') {
                $ids = array_map('intval', explode(',', $textIds));
                $ids = array_filter($ids, fn($id) => $id > 0);
                if (empty($ids)) {
                    return Response::error('No valid text IDs provided', 400);
                }
                return Response::success($this->formatGetTextScores($ids));
            }
            return Response::error('text_id or text_ids parameter is required', 400);
        } elseif ($frag1 === 'by-language') {
            if ($frag2 === '' || !ctype_digit($frag2)) {
                return Response::error('Expected Language ID after "by-language"', 404);
            }
            return Response::success($this->formatTextsByLanguage((int) $frag2, $params));
        } elseif ($frag1 === 'archived-by-language') {
            if ($frag2 === '' || !ctype_digit($frag2)) {
                return Response::error('Expected Language ID after "archived-by-language"', 404);
            }
            return Response::success($this->formatArchivedTextsByLanguage((int) $frag2, $params));
        } elseif ($frag1 !== '' && ctype_digit($frag1)) {
            $textId = (int) $frag1;
            if ($frag2 === 'words') {
                return Response::success($this->formatGetWords($textId));
            } elseif ($frag2 === 'print-items') {
                return Response::success($this->formatGetPrintItems($textId));
            } elseif ($frag2 === 'annotation') {
                return Response::success($this->formatGetAnnotation($textId));
            } elseif ($frag2 === 'book-context') {
                return $this->formatGetBookContext($textId);
            } elseif ($frag2 === 'audio') {
                return $this->formatGetAudio($textId);
            } elseif ($frag2 === '') {
                // GET /texts/{id} — editable fields for the bundled edit form.
                return $this->formatGetTextRecord($textId);
            }
            return Response::error(
                'Expected "words", "print-items", "annotation", "book-context", or "audio"',
                404
            );
        }
        return Response::error(
            'Expected "gutenberg-suggestions", "library-search", "library-preview", '
            . '"gdl-search", "scoring", "by-language", "archived-by-language", or text ID',
            404
        );
    }

    public function routePost(array $fragments, array $params): JsonResponse
    {
        $frag1 = $this->frag($fragments, 1);
        $frag2 = $this->frag($fragments, 2);

        if ($frag1 === 'extract-url') {
            $url = (string) ($params['url'] ?? '');
            if ($url === '') {
                return Response::error('url parameter is required', 400);
            }
            $titleHint = (string) ($params['titleHint'] ?? '');
            $extractor = new \Lukaisu\Shared\Infrastructure\Http\WebPageExtractor();
            $result = $extractor->extractFromUrl($url, $titleHint);
            if (isset($result['error'])) {
                return Response::error($result['error'], 422);
            }
            return Response::success($result);
        }

        if ($frag1 === 'extract-epub-url') {
            return $this->handleExtractEpubUrl($params);
        }

        if ($frag1 === 'check') {
            // POST /texts/check — parse-preview statistics (no text is created).
            return $this->handleCheck($params);
        }

        if ($frag1 === '') {
            // POST /texts — create a text (the bundled new-text page's
            // server-backed path). Mirrors the local router's createText.
            return $this->handleCreateText($params);
        }

        if (!ctype_digit($frag1)) {
            return Response::error('Text ID (Integer) Expected', 404);
        }

        $textId = (int) $frag1;

        switch ($frag2) {
            case 'annotation':
                return Response::success($this->formatSetAnnotation(
                    $textId,
                    (string) ($params['elem'] ?? ''),
                    (string) ($params['data'] ?? '')
                ));
            case 'audio-position':
                return Response::success($this->formatSetAudioPosition(
                    $textId,
                    (float) ($params['position'] ?? 0)
                ));
            case 'reading-position':
                return Response::success($this->formatSetTextPosition(
                    $textId,
                    (int) ($params['position'] ?? 0)
                ));
            default:
                return Response::error('Endpoint Not Found: ' . $frag2, 404);
        }
    }

    public function routePut(array $fragments, array $params): JsonResponse
    {
        $frag1 = $this->frag($fragments, 1);
        $frag2 = $this->frag($fragments, 2);

        if ($frag1 === 'bulk-action') {
            return $this->handleBulkAction($params);
        }

        if ($frag1 === '' || !ctype_digit($frag1)) {
            return Response::error('Text ID (Integer) Expected', 404);
        }

        $textId = (int) $frag1;

        switch ($frag2) {
            case '':
                // PUT /texts/{id} — save the bundled edit form.
                return $this->handleUpdateText($textId, $params);
            case 'display-mode':
                return Response::success($this->formatSetDisplayMode($textId, $params));
            case 'mark-all-wellknown':
                return Response::success($this->formatMarkAllWellKnown($textId));
            case 'mark-all-ignored':
                return Response::success($this->formatMarkAllIgnored($textId));
            default:
                return Response::error('Expected "display-mode", "mark-all-wellknown", or "mark-all-ignored"', 404);
        }
    }

    /**
     * GET /texts/{id} — one text's editable fields for the bundled edit form,
     * mirroring the local router's `getText` (and the offline `TextRecord`). The
     * server previously exposed single-text edit only as a web-route form, so
     * the bundle's `text-edit.html` could not load/save server-backed; this
     * closes that gap as part of the PHP-view cut-over.
     *
     * @param int $textId The text id.
     *
     * @return JsonResponse
     */
    private function formatGetTextRecord(int $textId): JsonResponse
    {
        $bindings = [$textId];
        $row = Connection::preparedFetchOne(
            "SELECT id, language_id, title, text, source_uri, audio_uri, archived_at
            FROM texts WHERE id = ?"
            . UserScopedQuery::forTablePrepared('texts', $bindings),
            $bindings
        );
        if ($row === null) {
            return Response::error('Text not found', 404);
        }
        return Response::success([
            'id' => (int) $row['id'],
            'langId' => (int) $row['language_id'],
            'title' => (string) $row['title'],
            'text' => (string) $row['text'],
            'sourceUri' => (string) ($row['source_uri'] ?? ''),
            'audioUri' => (string) ($row['audio_uri'] ?? ''),
            'tags' => $this->getTextTagNames($textId),
            'archived' => ($row['archived_at'] ?? null) !== null,
        ]);
    }

    /**
     * Tag names attached to a text (for the edit form's prefill).
     *
     * @param int $textId The text id.
     *
     * @return list<string>
     */
    private function getTextTagNames(int $textId): array
    {
        $bindings = [$textId];
        $rows = Connection::preparedFetchAll(
            "SELECT text_tags.text AS name
            FROM text_tag_map
            JOIN text_tags ON text_tags.id = text_tag_map.text_tag_id
            WHERE text_tag_map.text_id = ?"
            . UserScopedQuery::forTablePrepared('text_tags', $bindings),
            $bindings
        );
        $names = [];
        foreach ($rows as $record) {
            $names[] = (string) ($record['name'] ?? '');
        }
        return $names;
    }

    /**
     * PUT /texts/{id} — update the editable fields (re-parsing on body/language
     * change via UpdateText) and save tags. Mirrors the local router's
     * `updateText`; returns `{ updated, reparsed }`.
     *
     * @param int                  $textId The text id.
     * @param array<string, mixed> $params JSON body (TextUpdateRequest).
     *
     * @return JsonResponse
     */
    private function handleUpdateText(int $textId, array $params): JsonResponse
    {
        $langId = (int) ($params['langId'] ?? 0);
        if ($langId <= 0) {
            return Response::error('langId is required', 400);
        }
        $facade = Container::getInstance()->getTyped(TextFacade::class);
        $result = $facade->updateText(
            $textId,
            $langId,
            (string) ($params['title'] ?? ''),
            (string) ($params['text'] ?? ''),
            (string) ($params['audioUri'] ?? ''),
            (string) ($params['sourceUri'] ?? '')
        );
        if (isset($params['tags']) && is_array($params['tags'])) {
            // Present-but-empty `tags` clears the text's tags on update.
            TagsFacade::saveTextTags($textId, $this->normalizeTagNames($params['tags']));
        }
        return Response::success($result);
    }

    /**
     * POST /texts — create a text (the bundled new-text page's server-backed
     * path). Mirrors the local router's `createText` and the retired new-text
     * form: a plain create via TextFacade::createText, or an auto-split into a
     * book when the body exceeds the split threshold.
     *
     * @param array<string, mixed> $params JSON body (TextCreateRequest).
     *
     * @return JsonResponse
     */
    private function handleCreateText(array $params): JsonResponse
    {
        $langId = (int) ($params['langId'] ?? $params['language_id'] ?? 0);
        if ($langId <= 0) {
            return Response::error('langId is required', 400);
        }
        $title = (string) ($params['title'] ?? '');
        $text = (string) ($params['text'] ?? '');
        // TextsApi.create posts the server contract's snake_case keys
        // (source_uri / audio_uri); accept those and the camelCase aliases.
        $audioUri = (string) ($params['audioUri'] ?? $params['audio_uri'] ?? '');
        $sourceUri = (string) ($params['sourceUri'] ?? $params['source_uri'] ?? '');
        $tags = $this->normalizeTagNames($params['tags'] ?? null);

        $bookFacade = Container::getInstance()->getTyped(BookFacade::class);

        // Auto-split a long body into a book (parity with the retired form's
        // handleAutoSplitImport): one text per chapter, tags applied to each.
        if ($bookFacade->needsSplit($text)) {
            $result = $bookFacade->createBookFromText(
                $langId,
                $title,
                $text,
                null,
                $audioUri,
                $sourceUri,
                [],
                Globals::getCurrentUserId()
            );
            if (!$result['success']) {
                return Response::error($result['message'], 422);
            }
            if ($tags !== []) {
                foreach ($result['textIds'] as $chapterTextId) {
                    TagsFacade::saveTextTags($chapterTextId, $tags);
                }
            }
            return Response::success([
                'book' => true,
                'bookId' => $result['bookId'],
                'textIds' => $result['textIds'],
                'message' => $result['message'],
            ]);
        }

        $facade = Container::getInstance()->getTyped(TextFacade::class);
        $created = $facade->createText($langId, $title, $text, $audioUri, $sourceUri);
        $textId = (int) ($created['textId'] ?? 0);
        $message = (string) ($created['message'] ?? '');
        if ($textId <= 0) {
            return Response::error($message !== '' ? $message : 'Text creation failed', 422);
        }
        if ($tags !== []) {
            TagsFacade::saveTextTags($textId, $tags);
        }
        return Response::success([
            'id' => $textId,
            'message' => $message,
        ]);
    }

    /**
     * Normalize a raw `tags` payload into a clean list of non-empty tag names.
     *
     * @param mixed $tags Raw `tags` value from the JSON body.
     *
     * @return list<string>
     */
    private function normalizeTagNames(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }
        return array_values(array_filter(
            array_map(
                static fn(mixed $tag): string => is_scalar($tag) ? (string) $tag : '',
                $tags
            ),
            static fn(string $name): bool => $name !== ''
        ));
    }

    /**
     * POST /texts/check — parse-preview statistics for a raw text (the "check a
     * text" tool), mirroring the local router's `checkText`. Read-only: nothing
     * is persisted.
     *
     * @param array<string, mixed> $params JSON body (TextCheckRequest).
     *
     * @return JsonResponse
     */
    private function handleCheck(array $params): JsonResponse
    {
        $langId = (int) ($params['langId'] ?? 0);
        if ($langId <= 0) {
            return Response::error('langId is required', 400);
        }
        $text = (string) ($params['text'] ?? '');
        return Response::success(TextParsing::checkTextDetailed($text, $langId));
    }

    /**
     * Handle PUT /texts/bulk-action — archive or delete multiple texts.
     *
     * The JSON counterpart of the legacy mark-action form POST: it calls the
     * same TextFacade methods, which run under QueryBuilder's automatic
     * per-user scope, so a caller can only affect their own texts regardless of
     * the IDs sent. Only the two destructive actions are exposed here; tag /
     * review / reparse stay on the form path. Delivered as JSON so it works
     * against a configurable API base URL (a form POST would hit the page
     * origin, not the chosen server).
     *
     * @param array<string, mixed> $params { action: "archive"|"delete", ids: int[] }
     */
    private function handleBulkAction(array $params): JsonResponse
    {
        $action = (string) ($params['action'] ?? '');

        $rawIds = $params['ids'] ?? [];
        if (!is_array($rawIds)) {
            return Response::error('ids must be an array', 400);
        }
        $ids = array_values(array_filter(
            array_map(static fn ($id): int => (int) $id, $rawIds),
            static fn (int $id): bool => $id > 0
        ));
        if ($ids === []) {
            return Response::error('No text IDs provided', 400);
        }

        // `archived` scopes the action to archived texts (the ArchivedTexts
        // island); `tag` is required by the tag actions. This mirrors the marked-
        // action set the retired native /texts + /text/archived form handlers
        // served (TextCrudController::handleMarkAction / handleArchivedMarkAction).
        $archived = (bool) ($params['archived'] ?? false);
        $tag = trim((string) ($params['tag'] ?? ''));

        // Validate the action + tag up front, before resolving any facade, so
        // these branches stay DB-free (unit-tested without a database).
        $allowed = $archived
            ? ['delete', 'unarchive', 'add-tag', 'remove-tag']
            : ['archive', 'delete', 'rebuild', 'set-sentences', 'set-active-sentences', 'add-tag', 'remove-tag'];
        if (!in_array($action, $allowed, true)) {
            return Response::error('Expected one of: ' . implode(', ', $allowed), 400);
        }
        if (($action === 'add-tag' || $action === 'remove-tag') && $tag === '') {
            return Response::error('tag is required for tag actions', 400);
        }

        // Tag actions apply to both scopes; route to the matching tag facade.
        if ($action === 'add-tag') {
            return Response::success(
                $archived
                    ? TagsFacade::addTagToArchivedTexts($tag, $ids)
                    : TagsFacade::addTagToTexts($tag, $ids)
            );
        }
        if ($action === 'remove-tag') {
            if ($archived) {
                TagsFacade::removeTagFromArchivedTexts($tag, $ids);
            } else {
                TagsFacade::removeTagFromTexts($tag, $ids);
            }
            return Response::success(['count' => count($ids)]);
        }

        $facade = Container::getInstance()->getTyped(TextFacade::class);

        if ($archived) {
            return $action === 'delete'
                ? Response::success($facade->deleteArchivedTexts($ids))
                : Response::success($facade->unarchiveTexts($ids));
        }

        return match ($action) {
            'archive' => Response::success($facade->archiveTexts($ids)),
            'delete' => Response::success($facade->deleteTexts($ids)),
            'rebuild' => Response::success(['count' => $facade->rebuildTexts($ids)]),
            'set-sentences' => Response::success(['count' => $facade->setTermSentences($ids, false)]),
            default => Response::success(['count' => $facade->setTermSentences($ids, true)]),
        };
    }

    /**
     * Handle GET /texts/{id}/book-context — the reading screen's chapter nav.
     *
     * Returns the book/chapter context (titles, chapter index, prev/next text
     * ids) so a shell-free client can render the prev/next navigation that the
     * server otherwise bakes into read_desktop.php. The body is `{ "book": null }`
     * when the text is standalone (not part of a book) — a normal state, not an
     * error. The underlying query runs under QueryBuilder's per-user scope, so
     * the context of another user's text is never returned.
     */
    private function formatGetBookContext(int $textId): JsonResponse
    {
        $facade = Container::getInstance()->getTyped(BookFacade::class);

        return Response::success(['book' => $facade->getBookContextForText($textId)]);
    }

    /**
     * Handle GET /texts/{id}/audio — the reading screen's media-player config.
     *
     * Returns the per-text audio source and saved position plus the global
     * player settings, mirroring what MediaService bakes into the server-rendered
     * player, so a shell-free client can render the player itself. A 404 is
     * returned when the text does not exist or is not owned by the caller
     * (getTextForReading is per-user scoped); a present text with no audio yields
     * an empty `uri`.
     */
    private function formatGetAudio(int $textId): JsonResponse
    {
        $facade = Container::getInstance()->getTyped(TextFacade::class);
        $header = $facade->getTextForReading($textId);
        if ($header === null) {
            return Response::error('Text not found', 404);
        }

        // Defaults mirror MediaService::renderHtml5AudioPlayer (5s skip, 1.0x).
        $skip = Settings::get('currentplayerseconds');
        $rate = Settings::get('currentplaybackrate');

        return Response::success([
            'uri' => trim((string) ($header['audio_uri'] ?? '')),
            'position' => (float) ($header['audio_position'] ?? 0),
            'playerSettings' => [
                'repeatMode' => (bool) Settings::getZeroOrOne('currentplayerrepeatmode', 0),
                'skipSeconds' => $skip === '' ? 5 : (int) $skip,
                'playbackRate' => $rate === '' ? 10 : (int) $rate,
            ],
        ]);
    }

    // =========================================================================
    // Library Search (Project Gutenberg)
    // =========================================================================

    /**
     * Handle Gutenberg suggestion requests (popular books by language).
     *
     * @param array $params Request parameters (language_id, page)
     *
     * @return JsonResponse
     */
    private function handleGutenbergSuggestions(array $params): JsonResponse
    {
        $languageId = (int) ($params['language_id'] ?? 0);
        $page = max(1, (int) ($params['page'] ?? 1));

        if ($languageId <= 0) {
            return Response::error('language_id is required', 400);
        }

        $service = new GutenbergSuggestionService();
        $result = $service->getSuggestions($languageId, $page);

        if (isset($result['error'])) {
            return Response::error((string) $result['error'], 502);
        }

        return Response::success($result);
    }

    /**
     * Handle library search requests.
     *
     * @param array $params Request parameters (q, language_id, page)
     *
     * @return JsonResponse
     */
    private function handleLibrarySearch(array $params): JsonResponse
    {
        $query = trim((string) ($params['q'] ?? ''));
        $languageId = (int) ($params['language_id'] ?? 0);
        $page = max(1, (int) ($params['page'] ?? 1));

        $languageCode = null;
        if ($languageId > 0) {
            $languageCode = $this->resolveLanguageCode($languageId);
        }

        $client = new GutenbergClient();
        $result = $client->search($query, $languageCode, $page);

        if (isset($result['error'])) {
            return Response::error($result['error'], 502);
        }

        // Add quick difficulty tiers if a language is selected
        /** @var list<array{id: int, subjects: list<string>}> $resultBooks */
        $resultBooks = $result['results'] ?? [];
        if ($languageId > 0 && $resultBooks !== []) {
            /** @var array<int, list<string>> $subjectsMap */
            $subjectsMap = [];
            foreach ($resultBooks as $book) {
                $subjectsMap[$book['id']] = $book['subjects'] ?? [];
            }

            $service = new DifficultyEstimationService();
            $tiers = $service->estimateQuickTiers($languageId, $subjectsMap);

            $enriched = [];
            foreach ($resultBooks as $book) {
                $book['difficultyTier'] = $tiers[$book['id']] ?? 'medium';
                $enriched[] = $book;
            }
            $result['results'] = $enriched;
        }

        return Response::success($result);
    }

    /**
     * Handle library preview requests (accurate vocabulary coverage).
     *
     * @param array $params Request parameters (url, language_id)
     *
     * @return JsonResponse
     */
    private function handleLibraryPreview(array $params): JsonResponse
    {
        $url = trim((string) ($params['url'] ?? ''));
        $languageId = (int) ($params['language_id'] ?? 0);

        if ($url === '') {
            return Response::error('url parameter is required', 400);
        }

        if ($languageId <= 0) {
            return Response::error('language_id is required', 400);
        }

        $service = new DifficultyEstimationService();
        $result = $service->analyzeTextSample($url, $languageId);

        if (isset($result['error'])) {
            return Response::error($result['error'], 422);
        }

        return Response::success($result);
    }

    /**
     * Handle Global Digital Library search/browse requests.
     *
     * An empty query browses the catalog; difficulty tiers come from the
     * client (derived from each book's GDL reading level).
     *
     * @param array $params Request parameters (q, language_id, page)
     *
     * @return JsonResponse
     */
    private function handleGdlSearch(array $params): JsonResponse
    {
        $query = trim((string) ($params['q'] ?? ''));
        $languageId = (int) ($params['language_id'] ?? 0);
        $page = max(1, (int) ($params['page'] ?? 1));

        // GDL uses ISO 639-1 slugs for the common languages (en, fr, de, …),
        // so the same source_lang resolution as Gutenberg applies.
        $languageCode = null;
        if ($languageId > 0) {
            $languageCode = $this->resolveLanguageCode($languageId);
        }

        $client = new GdlClient();
        $result = $client->search($query, $languageCode, $page);

        if (isset($result['error'])) {
            return Response::error($result['error'], 502);
        }

        return Response::success($result);
    }

    /**
     * Handle reader-level requests (vocabulary size + beginner flag).
     *
     * Drives beginner-aware ordering of home-page suggestions: beginners see
     * the Global Digital Library's easy readers first, advanced learners see
     * Project Gutenberg first.
     *
     * @param array $params Request parameters (language_id)
     *
     * @return JsonResponse
     */
    private function handleReaderLevel(array $params): JsonResponse
    {
        $languageId = (int) ($params['language_id'] ?? 0);
        if ($languageId <= 0) {
            return Response::error('language_id is required', 400);
        }

        $service = new DifficultyEstimationService();
        return Response::success($service->getReaderProfile($languageId));
    }

    /**
     * Handle Global Digital Library ePUB import requests.
     *
     * Downloads the ePUB, extracts its text, and rejects image-only picture
     * books with too little readable content.
     *
     * @param array $params Request parameters (url)
     *
     * @return JsonResponse
     */
    private function handleExtractEpubUrl(array $params): JsonResponse
    {
        $url = (string) ($params['url'] ?? '');
        if ($url === '') {
            return Response::error('url parameter is required', 400);
        }

        $service = new GdlImportService();
        $result = $service->extractText($url);

        if (isset($result['error'])) {
            return Response::error($result['error'], 422);
        }

        return Response::success($result);
    }

    /**
     * Resolve a language ID to an ISO 639-1 code for Gutenberg.
     *
     * Tries source_lang first, then guesses from name.
     *
     * @param int $languageId Language ID
     *
     * @return string|null ISO code or null
     */
    private function resolveLanguageCode(int $languageId): ?string
    {
        $row = QueryBuilder::table('languages')
            ->select(['source_lang', 'name'])
            ->where('id', '=', $languageId)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        // Use explicit source language code if set.
        // Strip region/script subtags (e.g. "zh-CN" → "zh") because
        // Gutendex only accepts bare ISO 639-1 codes.
        $sourceLang = (string) ($row['source_lang'] ?? '');
        if ($sourceLang !== '') {
            $parts = explode('-', $sourceLang, 2);
            return strtolower($parts[0]);
        }

        // Fall back to guessing from language name
        $name = (string) ($row['name'] ?? '');
        return GutenbergClient::guessLanguageCode($name);
    }
}
