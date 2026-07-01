<?php

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Modules\Vocabulary\Application\Services\FrequencyImportService;
use Lukaisu\Modules\Vocabulary\Application\Services\FrequencyLanguageMap;
use Lukaisu\Modules\Vocabulary\Application\Services\WiktionaryEnrichmentService;

/**
 * Controller for the starter vocabulary import flow.
 *
 * Shown after language creation to offer importing common words
 * from the FrequencyWords project, with optional enrichment from
 * Wiktionary sources.
 */
class StarterVocabController extends BaseController
{
    private const ALLOWED_COUNTS = [50, 100, 500];
    private const ALLOWED_MODES = ['translation', 'definition'];

    private LanguageFacade $languageFacade;
    private FrequencyImportService $frequencyImportService;
    private WiktionaryEnrichmentService $enrichmentService;

    public function __construct(
        LanguageFacade $languageFacade,
        FrequencyImportService $frequencyImportService,
        WiktionaryEnrichmentService $enrichmentService
    ) {
        parent::__construct();
        $this->languageFacade = $languageFacade;
        $this->frequencyImportService = $frequencyImportService;
        $this->enrichmentService = $enrichmentService;
    }

    /**
     * Starter vocabulary bootstrap config (JSON).
     *
     * The starter-vocab UI is now a Svelte island shipped in the bundle
     * (`dist-app/starter-vocab.html`); the GET page route 302s there. The island
     * cannot compute the server-only bits — the language name, whether
     * FrequencyWords data exists, and the curated dictionaries filtered for the
     * language — so it fetches them here on mount. This mirrors the JSON blob the
     * retired `starter_vocab.php` view used to inline (minus the CSRF token, which
     * the island reads from `<meta name="csrf-token">`).
     *
     * Route: GET /api/v1/languages/{id}/starter-vocab/config
     * (dispatched by LanguageApiHandler@routeGet)
     *
     * @return JsonResponse
     */
    public function config(int $id): JsonResponse
    {
        $language = $this->languageFacade->getById($id);
        if ($language === null) {
            return JsonResponse::notFound('Language not found.');
        }

        $langName = $language->name();

        return JsonResponse::success([
            'langId' => $id,
            'langName' => $langName,
            'isAvailable' => FrequencyLanguageMap::isSupported($langName),
            'curatedDictionaries' => $this->loadCuratedDictionariesForLanguage($langName),
        ]);
    }

    /**
     * Import frequency words (AJAX).
     *
     * Route: POST /api/v1/languages/{id}/starter-vocab/import
     * (dispatched by LanguageApiHandler@routePost)
     *
     * @return JsonResponse
     */
    public function import(int $id): JsonResponse
    {
        $count = $this->paramInt('count', 1000);
        if ($count === null || !in_array($count, self::ALLOWED_COUNTS, true)) {
            return JsonResponse::error('Invalid count. Choose 50, 100, or 500.');
        }

        $language = $this->languageFacade->getById($id);
        if ($language === null) {
            return JsonResponse::notFound('Language not found.');
        }

        $langName = $language->name();
        if (!FrequencyLanguageMap::isSupported($langName)) {
            return JsonResponse::error(
                "Starter vocabulary is not available for $langName."
            );
        }

        try {
            $result = $this->frequencyImportService->importWords($id, $langName, $count);
        } catch (\RuntimeException $e) {
            error_log('StarterVocab import error: ' . $e->getMessage());
            return JsonResponse::error($e->getMessage(), 500);
        }

        return JsonResponse::success([
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'total' => $result['total'],
        ]);
    }

    /**
     * Enrich next batch of words (AJAX, called repeatedly).
     *
     * Route: POST /api/v1/languages/{id}/starter-vocab/enrich
     * (dispatched by LanguageApiHandler@routePost)
     *
     * Each call processes ~20 words. The client polls until
     * remaining === 0 or the user stops manually.
     *
     * @return JsonResponse
     */
    public function enrich(int $id): JsonResponse
    {
        $mode = $this->param('mode', 'translation');
        if (!in_array($mode, self::ALLOWED_MODES, true)) {
            return JsonResponse::error('Invalid mode. Choose "translation" or "definition".');
        }

        $language = $this->languageFacade->getById($id);
        if ($language === null) {
            return JsonResponse::notFound('Language not found.');
        }

        $langName = $language->name();
        if (!FrequencyLanguageMap::isSupported($langName)) {
            return JsonResponse::error(
                "Enrichment is not available for $langName."
            );
        }

        try {
            if ($mode === 'translation') {
                $result = $this->enrichmentService->enrichBatchTranslation($id, $langName);
            } else {
                $result = $this->enrichmentService->enrichBatchDefinition($id, $langName);
            }
        } catch (\Throwable $e) {
            error_log('StarterVocab enrich error: ' . $e->getMessage());
            return JsonResponse::error('Enrichment failed: ' . $e->getMessage(), 500);
        }

        return JsonResponse::success([
            'enriched' => $result['enriched'],
            'failed' => $result['failed'],
            'remaining' => $result['remaining'],
            'total' => $result['total'],
            'warning' => $result['warning'],
        ]);
    }

    /**
     * Load curated dictionaries filtered for a specific language.
     *
     * @param string $langName Language name (e.g., "German", "French")
     *
     * @return list<array<string, mixed>> Matching dictionary groups
     */
    private function loadCuratedDictionariesForLanguage(string $langName): array
    {
        $path = dirname(__DIR__, 4) . '/data/curated_dictionaries.json';
        if (!file_exists($path)) {
            return [];
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['dictionaries'])) {
            return [];
        }

        $langLower = strtolower($langName);
        $result = [];
        /** @var array<string, mixed> $group */
        foreach ($data['dictionaries'] as $group) {
            $groupName = strtolower((string) ($group['languageName'] ?? ''));
            if ($groupName === $langLower || str_contains($groupName, $langLower) || str_contains($langLower, $groupName)) {
                $result[] = $group;
            }
        }

        return $result;
    }

    /**
     * Skip starter vocab and go to text creation.
     *
     * Route: GET /languages/{id}/starter-vocab/skip
     */
    public function skip(int $id): void
    {
        header('Location: ' . url('/texts/new') . '?filterlang=' . $id);
        exit;
    }
}
