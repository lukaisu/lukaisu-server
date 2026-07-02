<?php

/**
 * Dictionary Controller
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Dictionary\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Dictionary\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Modules\Dictionary\Application\DictionaryFacade;
use Lukaisu\Modules\Dictionary\Application\Services\DictionaryImportFileResolver;
use Lukaisu\Shared\Infrastructure\Http\InputValidator;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use RuntimeException;

/**
 * Controller for local dictionary import.
 *
 * The server-rendered dictionary-list + import wizard (index/delete/preview +
 * Views/index.php) were dropped under the headless cut: the bundled client owns
 * those screens (dictionaries.html / dictionary-import.html) and manages
 * dictionaries via the local-dictionaries REST API. What remains here is the
 * multipart file import, dispatched by DictionaryApiHandler.
 */
class DictionaryController extends BaseController
{
    private DictionaryFacade $dictionaryFacade;

    /**
     * Create a new DictionaryController.
     *
     * @param DictionaryFacade $dictionaryFacade Dictionary facade
     */
    public function __construct(DictionaryFacade $dictionaryFacade)
    {
        parent::__construct();
        $this->dictionaryFacade = $dictionaryFacade;
    }

    /**
     * Import a dictionary file (multipart upload), returning JSON.
     *
     * Under the headless cut (Phase R) this moved off the cookie-authed native
     * form routes (`POST /dictionaries/import`, `POST /languages/{id}/…`) onto
     * POST /api/v1/local-dictionaries/import, dispatched by DictionaryApiHandler.
     * The DictionaryImportPage island posts a bearer-authed multipart body and
     * renders the outcome in place (the retired flow full-page-redirected to the
     * dictionaries list with a flash), so this answers with JSON —
     * {dictId, imported, langId} on success, {error} on failure. It reads the
     * language from the `lang_id`/`id` field, the file from `$_FILES['file']`,
     * and the per-format options exactly as the native form POST did.
     *
     * Route: POST /api/v1/local-dictionaries/import
     *
     * @param array<string, mixed> $params Parsed request body.
     *
     * @return JsonResponse
     */
    public function processImport(array $params): JsonResponse
    {
        // Support both route parameter and form field
        $langId = isset($params['id'])
            ? (int)$params['id']
            : ($this->paramInt('lang_id') ?? 0);
        $dictId = $this->paramInt('dict_id');
        $format = $this->param('format', 'csv');
        $dictName = $this->param('dict_name');

        if ($langId <= 0) {
            return JsonResponse::error('A valid language is required.');
        }

        // Create new dictionary if needed
        if ($dictId === null || $dictId <= 0) {
            if (empty($dictName)) {
                $dictName = 'Imported Dictionary ' . date('Y-m-d H:i');
            }
            $dictId = $this->dictionaryFacade->create($langId, $dictName, $format);
        }

        // Process uploaded file
        $uploadedFile = InputValidator::getUploadedFile('file');
        if ($uploadedFile === null) {
            return JsonResponse::error('No file was uploaded.');
        }

        $resolver = new DictionaryImportFileResolver();

        try {
            $resolved = $resolver->resolve($uploadedFile['tmp_name'], $uploadedFile['name'], $format);
            $importPath = $resolved['path'];
            $importName = $resolved['name'];

            $importer = $this->dictionaryFacade->getImporter($format, $importName);

            /** @psalm-suppress UndefinedClass Psalm incorrectly resolves namespace */
            if (!$importer->canImport($importPath, $importName)) {
                return JsonResponse::error('The uploaded file is not a valid ' . $format . ' dictionary.');
            }

            // Get import options from form
            $options = $this->getImportOptions($format);

            // Perform import
            /** @psalm-suppress UndefinedClass Psalm incorrectly resolves namespace */
            $entries = $importer->parse($importPath, $options);
            $count = $this->dictionaryFacade->addEntriesBatch($dictId, $entries);

            return JsonResponse::success([
                'dictId' => $dictId,
                'imported' => $count,
                'langId' => $langId,
            ]);
        } catch (RuntimeException $e) {
            return JsonResponse::error($e->getMessage(), 500);
        } finally {
            $resolver->cleanup();
        }
    }

    /**
     * Get import options from form parameters.
     *
     * @param string $format Import format
     *
     * @return array<string, mixed>
     */
    private function getImportOptions(string $format): array
    {
        $options = [];

        if ($format === 'csv' || $format === 'tsv') {
            $delimiter = $this->param('delimiter', ',');
            if ($delimiter === 'tab') {
                $delimiter = "\t";
            }
            $options['delimiter'] = $delimiter;
            $options['hasHeader'] = $this->param('has_header', 'yes') === 'yes';

            // Column mapping
            $termCol = $this->paramInt('term_column');
            $defCol = $this->paramInt('definition_column');
            $readingCol = $this->paramInt('reading_column');
            $posCol = $this->paramInt('pos_column');

            $options['columnMap'] = [
                'term' => $termCol ?? 0,
                'definition' => $defCol ?? 1,
                'reading' => $readingCol,
                'pos' => $posCol,
            ];
        } elseif ($format === 'json') {
            // Field mapping for JSON
            $termField = $this->param('term_field');
            $defField = $this->param('definition_field');
            $readingField = $this->param('reading_field');
            $posField = $this->param('pos_field');

            if (!empty($termField) || !empty($defField)) {
                $options['fieldMap'] = [
                    'term' => !empty($termField) ? $termField : null,
                    'definition' => !empty($defField) ? $defField : null,
                    'reading' => !empty($readingField) ? $readingField : null,
                    'pos' => !empty($posField) ? $posField : null,
                ];
            }
        }

        return $options;
    }
}
