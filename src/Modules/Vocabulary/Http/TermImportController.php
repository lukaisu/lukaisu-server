<?php

/**
 * Term Import Controller
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

use Lukaisu\Shared\Infrastructure\Utilities\StringUtils;
use Lukaisu\Shared\Infrastructure\Http\InputValidator;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Shared\Infrastructure\Database\Escaping;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Modules\Vocabulary\Application\Services\WordUploadService;
use Lukaisu\Modules\Vocabulary\Application\Services\FrequencyLanguageMap;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Modules\Dictionary\Application\DictionaryFacade;
use Lukaisu\Modules\Dictionary\Application\Services\DictionaryImportFileResolver;
use Lukaisu\Shared\UI\Helpers\PageLayoutHelper;
use RuntimeException;

/**
 * Controller for bulk translate and file import operations.
 *
 * Handles:
 * - /word/upload - Import terms from file
 * - /word/bulk-translate - Bulk translate terms
 */
class TermImportController extends VocabularyBaseController
{
    /**
     * Language facade.
     */
    private LanguageFacade $languageFacade;

    /**
     * Dictionary facade.
     *
     * @psalm-suppress PropertyNotSetInConstructor Set conditionally when dictionary features are available
     */
    private DictionaryFacade $dictionaryFacade;

    /**
     * Constructor.
     *
     * @param LanguageFacade|null   $languageFacade   Language facade
     * @param DictionaryFacade|null $dictionaryFacade Dictionary facade
     */
    public function __construct(
        ?LanguageFacade $languageFacade = null,
        ?DictionaryFacade $dictionaryFacade = null
    ) {
        parent::__construct();
        $this->languageFacade = $languageFacade ?? new LanguageFacade();
        if ($dictionaryFacade !== null) {
            $this->dictionaryFacade = $dictionaryFacade;
        }
    }

    /**
     * Bulk translate words (POST: save the chosen terms).
     *
     * The GET page is now the bundled Svelte `BulkTranslate` island: the
     * `/word/bulk-translate` GET route 302s into `dist-app/bulk-translate.html`
     * (see BundleController + routes.php), which fetches its data from
     * {@see config()} and posts the chosen terms back here. So this method only
     * handles the form POST; the next batch is loaded client-side by re-entering
     * the island (the saved terms drop out of the unknown-word query), not by
     * re-rendering a server form.
     *
     * @param array<string, string> $params Route parameters
     *
     * @return void
     */
    public function bulkTranslate(array $params): void
    {
        unset($params);
        $tid = InputValidator::getInt('tid', 0) ?? 0;
        $pos = InputValidator::getInt('offset');

        // Handle form submission (save terms)
        $termsArray = InputValidator::getArray('term');
        if (!empty($termsArray)) {
            /** @var array<int, array{lg: int, text: string, status: int, trans?: string}> $terms */
            $terms = $termsArray;
            $cnt = count($terms);

            PageLayoutHelper::renderPageStart($cnt . ' New Word' . ($cnt == 1 ? '' : 's') . ' Saved', false);
            // cleanUp when this was the last batch (no pagination offset carried).
            $this->handleBulkSave($terms, $tid, $pos === null);
        } else {
            PageLayoutHelper::renderPageStartNobody('Translate New Words');
        }

        PageLayoutHelper::renderPageEnd();
    }

    /**
     * Bulk-translate bootstrap config (JSON) for the Svelte island.
     *
     * The bulk-translate UI is now a Svelte island shipped in the bundle
     * (`dist-app/bulk-translate.html`); the GET page route 302s there. The island
     * cannot compute the server-only bits — the text's dictionaries and the page
     * of still-unknown words — so it fetches them here on mount. This mirrors the
     * JSON blob the retired `bulk_translate_form.php` view used to inline, plus
     * the term rows it rendered server-side. The chosen terms are posted back to
     * {@see bulkTranslate()} (the `saveUrl`); the CSRF token comes from
     * `<meta name="csrf-token">`.
     *
     * Route: GET /word/bulk-translate/config?tid=&offset=&sl=&tl=
     *
     * @param array<string, string> $params Route parameters (unused).
     *
     * @return JsonResponse
     */
    public function config(array $params): JsonResponse
    {
        unset($params);
        $tid = InputValidator::getInt('tid', 0) ?? 0;
        if ($tid <= 0) {
            return JsonResponse::error('Missing or invalid text id.');
        }
        $offset = InputValidator::getInt('offset', 0) ?? 0;
        if ($offset < 0) {
            $offset = 0;
        }
        $sl = InputValidator::getString('sl');
        $tl = InputValidator::getString('tl');

        $contextService = $this->getContextService();
        $discoveryService = $this->getDiscoveryService();
        $limit = (int) Settings::getWithDefault('set-ggl-translation-per-page') + 1;
        $dictionaries = $contextService->getLanguageDictionaries($tid);

        $res = $discoveryService->getUnknownWordsForBulkTranslate($tid, $offset, $limit);

        // Collect this page's terms; an extra (limit-th) row means there are more.
        $terms = [];
        $hasMore = false;
        $cnt = 0;
        foreach ($res as $record) {
            $cnt++;
            if ($cnt < $limit) {
                $terms[] = [
                    'word' => (string) ($record['word'] ?? ''),
                    'languageId' => (int) ($record['language_id'] ?? 0),
                ];
            } else {
                $hasMore = true;
            }
        }
        $nextOffset = $hasMore ? $offset + $limit - 1 : null;

        return JsonResponse::success([
            'saveUrl' => url('/word/bulk-translate'),
            'tid' => $tid,
            'sourceLanguage' => $sl !== '' ? $sl : null,
            'targetLanguage' => $tl !== '' ? $tl : null,
            'offset' => $offset,
            'dictionaries' => [
                'dict1' => $dictionaries['dict1'] ?? '',
                'dict2' => $dictionaries['dict2'] ?? '',
                'translate' => $dictionaries['translate'] ?? '',
            ],
            'terms' => $terms,
            'nextOffset' => $nextOffset,
        ]);
    }

    /**
     * Handle saving bulk translated terms.
     *
     * @param array<int, array{lg: int, text: string, status: int, trans?: string}> $terms Array of term data
     * @param int  $tid     Text ID
     * @param bool $cleanUp Whether to clean up right frames after save
     *
     * @return void
     *
     * @psalm-suppress UnusedParam $tid and $cleanUp are used in included view file
     * @psalm-suppress UnresolvableInclude Path computed from viewPath property
     */
    private function handleBulkSave(array $terms, int $tid, bool $cleanUp): void
    {
        $bulkService = $this->getBulkService();
        $maxWoId = $bulkService->bulkSaveTerms($terms);

        $tooltipMode = Settings::getWithDefault('set-tooltip-mode');
        $res = $bulkService->getNewWordsAfter($maxWoId);

        // Link new words to text items
        $linkingService = new \Lukaisu\Modules\Vocabulary\Application\Services\WordLinkingService();
        $linkingService->linkNewWordsToTextItems($maxWoId);

        // Prepare data for view
        /** @var list<array<string, mixed>> $newWords */
        $newWords = [];
        foreach ($res as $record) {
            $record['hex'] = StringUtils::toClassName(
                Escaping::prepareTextdata((string)$record['text_lc'])
            );
            $record['translation'] = (string)$record['translation'];
            $newWords[] = $record;
        }

        $todoContent = $this->getTextStatisticsService()->getTodoWordsContent($tid);

        include $this->viewPath . 'bulk_save_result.php';
    }

    /**
     * Upload words from file (POST): import terms or a dictionary file.
     *
     * The GET page is the bundled Svelte `WordUpload` island (the `/word/upload`
     * GET route 302s into `dist-app/word-upload.html`); this method now only
     * serves the island's manual-upload `fetch()` POST and always answers with
     * JSON. The Svelte manual tab submits exactly two operations, keyed by the
     * clicked submit button's `op` value: `ImportDictionary` (upload a dictionary
     * file → {@see handleDictionaryImport}) or `Import` (a CSV/TSV/pasted term
     * list → {@see handleUploadImport}); anything else falls through to the term
     * import. Both return {lastUpdate, rtl, recno} so the island can render the
     * imported-terms table client-side (the retired `upload_result.php` view's
     * job), or {error} on failure.
     *
     * @param array<string, string> $params Route parameters (unused).
     *
     * @return JsonResponse
     */
    public function upload(array $params): JsonResponse
    {
        unset($params);

        $op = InputValidator::getString('op');
        if ($op === 'ImportDictionary') {
            return $this->handleDictionaryImport();
        }

        return $this->handleUploadImport();
    }

    /**
     * Word-upload bootstrap config (JSON) for the Svelte island.
     *
     * The word-upload UI is now a Svelte island shipped in the bundle
     * (`dist-app/word-upload.html`); the GET page route 302s there. The island
     * cannot compute the server-only bits — the current language (id + name),
     * whether FrequencyWords data exists for it, the curated dictionaries
     * registry, and the base-path-correct import endpoints — so it fetches them
     * here on mount. This mirrors the JSON blobs the retired `upload_form.php`
     * view used to inline (minus the CSRF token, which the island reads from
     * `<meta name="csrf-token">`). The manual upload still posts a native
     * multipart form to {@see upload()} (the `uploadUrl`).
     *
     * Route: GET /word/upload/config
     *
     * @param array<string, string> $params Route parameters (unused).
     *
     * @return JsonResponse
     */
    public function uploadConfig(array $params): JsonResponse
    {
        unset($params);

        $currentLanguage = Settings::get('currentlanguage');
        $langId = 0;
        $langName = '';
        $isFrequencyAvailable = false;
        if ($currentLanguage !== '') {
            $langId = (int) $currentLanguage;
            $langName = $this->languageFacade->getLanguageName($langId);
            $isFrequencyAvailable = FrequencyLanguageMap::isSupported($langName);
        }

        return JsonResponse::success([
            'uploadUrl' => url('/word/upload'),
            'langId' => $langId,
            'langName' => $langName,
            'isFrequencyAvailable' => $isFrequencyAvailable,
            'importUrl' => $langId > 0 ? url('/languages/' . $langId . '/starter-vocab/import') : '',
            'enrichUrl' => $langId > 0 ? url('/languages/' . $langId . '/starter-vocab/enrich') : '',
            'translationDelimiter' => Settings::getWithDefault('set-term-translation-delimiters'),
            'curatedDictionaries' => $this->loadCuratedDictionaries(),
        ]);
    }

    /**
     * Load curated dictionaries from the JSON registry.
     *
     * @return list<array<string, mixed>>
     */
    private function loadCuratedDictionaries(): array
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
        /** @var list<array<string, mixed>> */
        $dictionaries = $data['dictionaries'];
        return $dictionaries;
    }

    /**
     * Handle the word import operation (op=Import).
     *
     * Imports a CSV/TSV/pasted term list and answers with the JSON the Svelte
     * island needs to render the imported-terms table client-side:
     * `{lastUpdate, rtl, recno}` — the pre-import high-water mark, the language's
     * text direction, and the count of terms this import added (the island then
     * pages them from `GET /api/v1/terms/imported`). Errors return `{error}`.
     *
     * @return JsonResponse
     */
    private function handleUploadImport(): JsonResponse
    {
        $uploadService = $this->getUploadService();
        $tabType = InputValidator::getString("Tab");
        if ($tabType === '') {
            $tabType = 'c';
        }
        $langId = InputValidator::getInt("id", 0) ?? 0;

        if ($langId === 0) {
            return JsonResponse::error('No language selected');
        }

        $langData = $uploadService->getLanguageData($langId);
        if ($langData === null) {
            return JsonResponse::error('Invalid language');
        }

        $removeSpaces = (bool) $langData['remove_spaces'];

        // Parse column mapping
        $columns = [
            1 => InputValidator::getString("Col1"),
            2 => InputValidator::getString("Col2"),
            3 => InputValidator::getString("Col3"),
            4 => InputValidator::getString("Col4"),
            5 => InputValidator::getString("Col5"),
        ];
        $columns = array_unique($columns);

        $parsed = $uploadService->parseColumnMapping($columns, $removeSpaces);
        /** @var array<int, string> $col */
        $col = $parsed['columns'];
        /** @var array{txt: int, tr: int, ro: int, se: int, tl: int} $fields */
        $fields = $parsed['fields'];

        // Check for file upload vs text input
        $uploadedFile = InputValidator::getUploadedFile('thefile');

        // Get or create the input file
        $uploadText = InputValidator::getString("Upload");
        $createdTempFile = false;
        if ($uploadedFile !== null) {
            $fileName = $uploadedFile["tmp_name"];
        } else {
            if ($uploadText === '') {
                return JsonResponse::error('No data to import');
            }
            $fileName = $uploadService->createTempFile($uploadText);
            $createdTempFile = true;
        }

        try {
            $ignoreFirst = InputValidator::getString("IgnFirstLine") === '1';
            $overwrite = InputValidator::getInt("Over", 0) ?? 0;
            $status = InputValidator::getInt("status", 1) ?? 1;
            $translDelim = InputValidator::getString("transl_delim");

            // Get last update timestamp before import
            $lastUpdate = $uploadService->getLastWordUpdate() ?? '';

            if ($fields["txt"] > 0) {
                // Import terms
                $this->importTerms(
                    $uploadService,
                    $langId,
                    $fields,
                    $col,
                    $tabType,
                    $fileName,
                    $status,
                    $overwrite,
                    $ignoreFirst,
                    $translDelim,
                    $lastUpdate
                );

                return JsonResponse::success([
                    'lastUpdate' => $lastUpdate,
                    'rtl' => $uploadService->isRightToLeft($langId),
                    'recno' => $uploadService->countImportedTerms($lastUpdate),
                ]);
            } elseif ($fields["tl"] > 0) {
                // Import tags only (no term column): nothing to page, recno = 0.
                $uploadService->importTagsOnly(['tl' => $fields['tl']], $tabType, $fileName, $ignoreFirst);
                return JsonResponse::success([
                    'lastUpdate' => $lastUpdate,
                    'rtl' => $uploadService->isRightToLeft($langId),
                    'recno' => 0,
                ]);
            }

            return JsonResponse::error('No term column specified');
        } finally {
            // Clean up temp file if we created it
            if ($createdTempFile && file_exists($fileName)) {
                unlink($fileName);
            }
        }
    }

    /**
     * Handle dictionary file import (op=ImportDictionary).
     *
     * Imports an uploaded dictionary file (CSV/JSON/StarDict) as a reference
     * dictionary and materialises status-1 vocabulary terms from its entries.
     * Answers with the same `{lastUpdate, rtl, recno}` shape as the term import
     * so the island renders the newly created terms in its table: `lastUpdate` is
     * snapshotted before the vocabulary is created and `recno` counts the terms
     * added past it. Errors return `{error}`.
     *
     * @return JsonResponse
     */
    private function handleDictionaryImport(): JsonResponse
    {
        $langId = InputValidator::getInt("id", 0) ?? 0;
        if ($langId === 0) {
            return JsonResponse::error('No language selected');
        }

        $format = InputValidator::getString('dict_format') ?: 'csv';
        $dictName = InputValidator::getString('dict_name');

        $uploadedFile = InputValidator::getUploadedFile('dict_file');
        if ($uploadedFile === null) {
            return JsonResponse::error('No file uploaded');
        }

        if (empty($dictName)) {
            $dictName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME) ?: 'Imported Dictionary';
        }

        $uploadService = $this->getUploadService();
        // Snapshot the high-water mark before creating vocabulary so we can count
        // (and page) exactly the terms this import adds.
        $lastUpdate = $uploadService->getLastWordUpdate() ?? '';

        $resolver = new DictionaryImportFileResolver();

        try {
            $resolved = $resolver->resolve($uploadedFile['tmp_name'], $uploadedFile['name'], $format);
            $importPath = $resolved['path'];
            $importName = $resolved['name'];

            $importer = $this->dictionaryFacade->getImporter($format, $importName);

            if (!$importer->canImport($importPath, $importName)) {
                return JsonResponse::error('Invalid file format');
            }

            // Build import options
            $options = $this->getDictImportOptions($format);

            $dictId = $this->dictionaryFacade->create($langId, $dictName, $format);
            $entries = $importer->parse($importPath, $options);
            $this->dictionaryFacade->addEntriesBatch($dictId, $entries);

            // Create vocabulary terms (status 1) from dictionary entries
            $this->dictionaryFacade->createVocabularyFromEntries($dictId, $langId);

            // Auto-enable local dict mode if currently online-only
            $this->dictionaryFacade->autoEnableLocalDictMode($langId);
        } catch (RuntimeException $e) {
            return JsonResponse::error($e->getMessage());
        } finally {
            $resolver->cleanup();
        }

        return JsonResponse::success([
            'lastUpdate' => $lastUpdate,
            'rtl' => $uploadService->isRightToLeft($langId),
            'recno' => $uploadService->countImportedTerms($lastUpdate),
        ]);
    }

    /**
     * Get dictionary import options from form parameters.
     *
     * @param string $format Import format
     *
     * @return array<string, mixed>
     */
    private function getDictImportOptions(string $format): array
    {
        $options = [];

        if ($format === 'csv' || $format === 'tsv') {
            $delimiter = InputValidator::getString('dict_delimiter') ?: ',';
            if ($delimiter === 'tab') {
                $delimiter = "\t";
            }
            $options['delimiter'] = $delimiter;
            $options['hasHeader'] = InputValidator::getString('dict_has_header') !== 'no';

            $termCol = InputValidator::getInt('dict_term_column');
            $defCol = InputValidator::getInt('dict_definition_column');

            $options['columnMap'] = [
                'term' => $termCol ?? 0,
                'definition' => $defCol ?? 1,
            ];
        }

        return $options;
    }

    /**
     * Import terms from the uploaded file.
     *
     * @param WordUploadService       $uploadService  The upload service
     * @param int                     $langId         Language ID
     * @param array{txt: int, tr: int, ro: int, se: int, tl: int} $fields Field indexes
     * @param array<int, string>      $col            Column mapping
     * @param string                  $tabType        Tab type (c, t, h)
     * @param string                  $fileName       Path to input file
     * @param int                     $status         Word status
     * @param int                     $overwrite      Overwrite mode
     * @param bool                    $ignoreFirst    Ignore first line
     * @param string                  $translDelim    Translation delimiter
     * @param string                  $lastUpdate     Last update timestamp
     *
     * @return void
     */
    private function importTerms(
        WordUploadService $uploadService,
        int $langId,
        array $fields,
        array $col,
        string $tabType,
        string $fileName,
        int $status,
        int $overwrite,
        bool $ignoreFirst,
        string $translDelim,
        string $lastUpdate
    ): void {
        $columnsClause = '(' . rtrim(implode(',', $col), ',') . ')';
        $delimiter = $uploadService->getSqlDelimiter($tabType);

        // Use simple import for no tags and no overwrite, complete import otherwise
        if ($fields["tl"] == 0 && $overwrite == 0) {
            $uploadService->importSimple(
                $langId,
                $fields,
                $columnsClause,
                $delimiter,
                $fileName,
                $status,
                $ignoreFirst
            );
        } else {
            $uploadService->importComplete(
                $langId,
                $fields,
                $columnsClause,
                $delimiter,
                $fileName,
                $status,
                $overwrite,
                $ignoreFirst,
                $translDelim,
                $tabType
            );
        }

        // Post-import processing
        \Lukaisu\Shared\Infrastructure\Database\Maintenance::initWordCount();
        $uploadService->linkWordsToTextItems();
        $uploadService->handleMultiwords($langId, $lastUpdate);
    }
}
