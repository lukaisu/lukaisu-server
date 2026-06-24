<?php

/**
 * Word CRUD Service - Basic CRUD operations for words
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\Services;

use Lukaisu\Modules\Vocabulary\Domain\Term;
use Lukaisu\Modules\Vocabulary\Infrastructure\MySqlTermRepository;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\Escaping;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Activity\Infrastructure\MySqlActivityRepository;

/**
 * Service for basic CRUD operations on words/terms.
 *
 * Handles:
 * - Create, update, delete operations
 * - Find by ID or text
 * - Get word data and details
 * - Inline edit operations
 *
 * @since 3.0.0
 */
class WordCrudService
{
    private MySqlTermRepository $repository;
    private MySqlActivityRepository $activityRepository;

    /**
     * Constructor.
     *
     * @param MySqlTermRepository|null     $repository         Term repository
     * @param MySqlActivityRepository|null $activityRepository Activity repository
     */
    public function __construct(
        ?MySqlTermRepository $repository = null,
        ?MySqlActivityRepository $activityRepository = null
    ) {
        $this->repository = $repository ?? new MySqlTermRepository();
        $this->activityRepository = $activityRepository ?? new MySqlActivityRepository();
    }

    /**
     * Create a new word/term.
     *
     * @param array $data Word data with keys:
     *                    - language_id: Language ID
     *                    - text: Term text
     *                    - status: Learning status (1-5)
     *                    - translation: Translation text
     *                    - sentence: Example sentence
     *                    - notes: Personal notes
     *                    - romanization: Romanization/phonetic
     *                    - lemma: Lemma/base form (optional)
     *
     * @return array{id: int, message: string, success: bool, textlc: string, text: string}
     */
    public function create(array $data): array
    {
        $text = trim(Escaping::prepareTextdata((string) $data['text']));
        $textlc = mb_strtolower($text, 'UTF-8');
        $translation = $this->normalizeTranslation((string) ($data['translation'] ?? ''));

        // Handle lemma field
        $lemma = isset($data['lemma']) && $data['lemma'] !== ''
            ? trim((string) $data['lemma'])
            : null;
        $lemmaLc = $lemma !== null ? mb_strtolower($lemma, 'UTF-8') : null;

        try {
            $scoreColumns = TermStatusService::makeScoreRandomInsertUpdate('iv');
            $scoreValues = TermStatusService::makeScoreRandomInsertUpdate('id');

            $bindings = [
                $data['language_id'],
                $textlc,
                $text,
                $lemma,
                $lemmaLc,
                $data['status'],
                $translation,
                ExportService::replaceTabNewline((string) ($data['sentence'] ?? '')),
                ExportService::replaceTabNewline((string) ($data['notes'] ?? '')),
                (string) ($data['romanization'] ?? '')
            ];
            $sql = "INSERT INTO words (
                    language_id, text_lc, text, lemma, lemma_lc, status, translation,
                    sentence, notes, romanization, status_changed_at, {$scoreColumns}"
                    . UserScopedQuery::insertColumn('words')
                . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), {$scoreValues}"
                    . UserScopedQuery::insertValuePrepared('words', $bindings)
                . ")";

            $wid = (int) Connection::preparedInsert($sql, $bindings);

            $this->activityRepository->incrementTermsCreated();

            return [
                'id' => $wid,
                'message' => __('vocabulary.flash.term_saved'),
                'success' => true,
                'textlc' => $textlc,
                'text' => $text
            ];
        } catch (\RuntimeException $e) {
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'Duplicate entry') !== false) {
                $message = __('vocabulary.flash.error_prefix', ['message' => 'Duplicate entry for "' . $textlc . '"']);
            } else {
                $message = __('vocabulary.flash.error_prefix', ['message' => $errorMsg]);
            }

            return [
                'id' => 0,
                'message' => $message,
                'success' => false,
                'textlc' => $textlc,
                'text' => $text
            ];
        }
    }

    /**
     * Update an existing word/term.
     *
     * @param int   $wordId Word ID
     * @param array $data   Word data (same keys as create())
     *
     * @return array{id: int, message: string, success: bool, textlc: string, text: string}
     */
    public function update(int $wordId, array $data): array
    {
        $text = trim(Escaping::prepareTextdata((string) $data['text']));
        $textlc = mb_strtolower($text, 'UTF-8');
        $translation = $this->normalizeTranslation((string) ($data['translation'] ?? ''));
        $sentence = ExportService::replaceTabNewline((string) ($data['sentence'] ?? ''));
        $notes = ExportService::replaceTabNewline((string) ($data['notes'] ?? ''));
        $roman = (string) ($data['romanization'] ?? '');

        // Handle lemma field
        $lemma = isset($data['lemma']) && $data['lemma'] !== ''
            ? trim((string) $data['lemma'])
            : null;
        $lemmaLc = $lemma !== null ? mb_strtolower($lemma, 'UTF-8') : null;

        $scoreUpdate = TermStatusService::makeScoreRandomInsertUpdate('u');

        $bindings = [$text, $translation, $sentence, $notes, $roman, $lemma, $lemmaLc];

        if (isset($data['WoOldStatus']) && $data['WoOldStatus'] != $data['status']) {
            // Status changed - update status and timestamp
            $bindings[] = (int) $data['status'];
            $bindings[] = $wordId;
            $sql = "UPDATE words SET
                text = ?, translation = ?, sentence = ?, notes = ?, romanization = ?,
                lemma = ?, lemma_lc = ?,
                status = ?, status_changed_at = NOW(), {$scoreUpdate}
                WHERE id = ?"
                . UserScopedQuery::forTablePrepared('words', $bindings);
            Connection::preparedExecute($sql, $bindings);
        } else {
            // Status unchanged
            $bindings[] = $wordId;
            $sql = "UPDATE words SET
                text = ?, translation = ?, sentence = ?, notes = ?, romanization = ?,
                lemma = ?, lemma_lc = ?, {$scoreUpdate}
                WHERE id = ?"
                . UserScopedQuery::forTablePrepared('words', $bindings);
            Connection::preparedExecute($sql, $bindings);
        }

        return [
            'id' => $wordId,
            'message' => __('vocabulary.flash.term_updated_short'),
            'success' => true,
            'textlc' => $textlc,
            'text' => $text
        ];
    }

    /**
     * Find a word by ID.
     *
     * @param int $wordId Word ID
     *
     * @return array|null Word data or null if not found
     */
    public function findById(int $wordId): ?array
    {
        $term = $this->repository->find($wordId);
        if ($term === null) {
            return null;
        }

        return $this->termEntityToArray($term);
    }

    /**
     * Find a word by text and language.
     *
     * @param string $textlc Lowercase text
     * @param int    $langId Language ID
     *
     * @return int|null Word ID or null if not found
     */
    public function findByText(string $textlc, int $langId): ?int
    {
        $term = $this->repository->findByTextLc($langId, $textlc);
        return $term !== null ? $term->id()->toInt() : null;
    }

    /**
     * Delete a word by ID.
     *
     * @param int $wordId Word ID to delete
     *
     * @return void
     */
    public function delete(int $wordId): void
    {
        // Delete multi-word text items first (before word deletion triggers FK SET NULL)
        QueryBuilder::table('word_occurrences')
            ->where('word_id', '=', $wordId)
            ->where('word_count', '>', 1)
            ->deletePrepared();

        // Delete the word - FK constraints handle:
        // - Single-word word_occurrences.word_id set to NULL (ON DELETE SET NULL)
        // - word_tag_map deleted (ON DELETE CASCADE)
        QueryBuilder::table('words')
            ->where('id', '=', $wordId)
            ->deletePrepared();
    }

    /**
     * Get word count for a term.
     *
     * @param int $wordId Word ID
     *
     * @return int Word count
     */
    public function getWordCount(int $wordId): int
    {
        $bindings = [$wordId];
        /** @var int $count */
        $count = (int) Connection::preparedFetchValue(
            "SELECT word_count FROM words WHERE id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'word_count'
        );
        return $count;
    }

    /**
     * Get word data including translation and romanization.
     *
     * @param int $wordId Word ID
     *
     * @return array{text: string, translation: string, romanization: string}|null
     */
    public function getWordData(int $wordId): ?array
    {
        $bindings = [$wordId];
        $record = Connection::preparedFetchOne(
            "SELECT text, translation, romanization
             FROM words WHERE id = ?"
             . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings
        );

        if ($record === null) {
            return null;
        }

        return [
            'text' => (string) $record['text'],
            'translation' => ExportService::replaceTabNewline((string) $record['translation']),
            'romanization' => (string) $record['romanization']
        ];
    }

    /**
     * Get a single word's text by ID.
     *
     * @param int $wordId Word ID
     *
     * @return string|null Word text or null if not found
     */
    public function getWordText(int $wordId): ?string
    {
        $bindings = [$wordId];
        /** @var string|null $term */
        $term = Connection::preparedFetchValue(
            "SELECT text FROM words WHERE id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'text'
        );
        return $term;
    }

    /**
     * Get full word details for display.
     *
     * @param int $wordId Word ID
     *
     * @return array|null Word details or null if not found
     */
    public function getWordDetails(int $wordId): ?array
    {
        $bindings = [$wordId];
        $record = Connection::preparedFetchOne(
            "SELECT language_id, text, translation, sentence, notes, romanization, status
             FROM words WHERE id = ?"
             . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings
        );

        if ($record === null) {
            return null;
        }

        $translation = ExportService::replaceTabNewline((string) $record['translation']);
        if ($translation === '*') {
            $translation = '';
        }

        return [
            'langId' => (int) $record['language_id'],
            'text' => (string) $record['text'],
            'translation' => $translation,
            'sentence' => (string) $record['sentence'],
            'notes' => (string) ($record['notes'] ?? ''),
            'romanization' => (string) $record['romanization'],
            'status' => (int) $record['status']
        ];
    }

    /**
     * Update translation for a word (inline edit).
     *
     * @param int    $wordId Word ID
     * @param string $value  New translation value
     *
     * @return string Updated translation value
     */
    public function updateTranslation(int $wordId, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            $value = '*';
        }

        $bindings = [ExportService::replaceTabNewline($value), $wordId];
        $sql = "UPDATE words SET translation = ? WHERE id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings);
        Connection::preparedExecute($sql, $bindings);

        $bindings = [$wordId];
        /** @var string $translation */
        $translation = (string) Connection::preparedFetchValue(
            "SELECT translation FROM words WHERE id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'translation'
        );
        return $translation;
    }

    /**
     * Update romanization for a word (inline edit).
     *
     * @param int    $wordId Word ID
     * @param string $value  New romanization value
     *
     * @return string Updated romanization value (returns '*' if empty for display)
     */
    public function updateRomanization(int $wordId, string $value): string
    {
        $bindings = [trim($value), $wordId];
        $sql = "UPDATE words SET romanization = ? WHERE id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings);
        Connection::preparedExecute($sql, $bindings);

        $bindings = [$wordId];
        /** @var string $roman */
        $roman = (string) Connection::preparedFetchValue(
            "SELECT romanization FROM words WHERE id = ?"
            . UserScopedQuery::forTablePrepared('words', $bindings),
            $bindings,
            'romanization'
        );
        return $roman === '' ? '*' : $roman;
    }

    /**
     * Normalize translation value.
     *
     * @param string $translation Raw translation
     *
     * @return string Normalized translation (empty becomes '*')
     */
    private function normalizeTranslation(string $translation): string
    {
        $translation = trim(ExportService::replaceTabNewline($translation));
        return $translation === '' ? '*' : $translation;
    }

    /**
     * Convert a Term entity to an array format for backward compatibility.
     *
     * @param Term $term Term entity
     *
     * @return array Term data as associative array
     */
    private function termEntityToArray(Term $term): array
    {
        // Preserve null semantics for sentence - empty string means null in DB
        $sentence = $term->sentence();
        if ($sentence === '') {
            $sentence = null;
        }

        // Preserve null semantics for notes - empty string means null in DB
        $notes = $term->notes();
        if ($notes === '') {
            $notes = null;
        }

        return [
            'id' => $term->id()->toInt(),
            'language_id' => $term->languageId()->toInt(),
            'text' => $term->text(),
            'text_lc' => $term->textLowercase(),
            'lemma' => $term->lemma(),
            'lemma_lc' => $term->lemmaLc(),
            'status' => $term->status()->toInt(),
            'translation' => $term->translation(),
            'sentence' => $sentence,
            'notes' => $notes,
            'romanization' => $term->romanization(),
            'word_count' => $term->wordCount(),
            'created_at' => $term->createdAt()->format('Y-m-d H:i:s'),
            'status_changed_at' => $term->statusChangedAt()->format('Y-m-d H:i:s'),
            'today_score' => $term->todayScore(),
            'tomorrow_score' => $term->tomorrowScore(),
            'random' => $term->random(),
        ];
    }
}
