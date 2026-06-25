<?php

/**
 * Local Dictionary Service - Business logic for local dictionary management
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Dictionary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Dictionary\Application\Services;

use DateTimeImmutable;
use Lukaisu\Modules\Dictionary\Domain\LocalDictionary;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;

/**
 * Service class for managing local dictionaries.
 *
 * Handles CRUD operations for local dictionaries and entries,
 * as well as term lookups.
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Dictionary\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class LocalDictionaryService
{
    /**
     * Batch size for bulk inserts.
     */
    private const BATCH_SIZE = 1000;

    /**
     * Create a new local dictionary.
     *
     * @param int         $languageId   Language ID
     * @param string      $name         Dictionary name
     * @param string      $sourceFormat Source format (csv, json, stardict)
     * @param string|null $description  Optional description
     *
     * @return int The new dictionary ID
     */
    public function create(
        int $languageId,
        string $name,
        string $sourceFormat = 'csv',
        ?string $description = null
    ): int {
        $userId = Globals::getCurrentUserId();

        $dictionary = LocalDictionary::create($languageId, $name, $sourceFormat, $userId);
        if ($description !== null) {
            $dictionary->setDescription($description);
        }

        $data = [
            'language_id' => $dictionary->languageId(),
            'name' => $dictionary->name(),
            'description' => $dictionary->description(),
            'source_format' => $dictionary->sourceFormat(),
            'entry_count' => 0,
            'priority' => $dictionary->priority(),
            'enabled' => $dictionary->isEnabled() ? 1 : 0,
            'user_id' => $dictionary->userId(),
        ];

        QueryBuilder::table('local_dictionaries')->insertPrepared($data);
        return (int) Connection::lastInsertId();
    }

    /**
     * Get a dictionary by ID.
     *
     * @param int $dictId Dictionary ID
     *
     * @return LocalDictionary|null
     */
    public function getById(int $dictId): ?LocalDictionary
    {
        $record = QueryBuilder::table('local_dictionaries')
            ->where('id', '=', $dictId)
            ->firstPrepared();

        if ($record === null) {
            return null;
        }

        return $this->hydrateFromRecord($record);
    }

    /**
     * Get all dictionaries for a language.
     *
     * @param int $languageId Language ID
     *
     * @return LocalDictionary[]
     */
    public function getForLanguage(int $languageId): array
    {
        $records = QueryBuilder::table('local_dictionaries')
            ->where('language_id', '=', $languageId)
            ->where('enabled', '=', 1)
            ->orderBy('priority', 'ASC')
            ->getPrepared();

        return array_map([$this, 'hydrateFromRecord'], $records);
    }

    /**
     * Get all dictionaries for a language (including disabled).
     *
     * @param int $languageId Language ID
     *
     * @return LocalDictionary[]
     */
    public function getAllForLanguage(int $languageId): array
    {
        $records = QueryBuilder::table('local_dictionaries')
            ->where('language_id', '=', $languageId)
            ->orderBy('priority', 'ASC')
            ->getPrepared();

        return array_map([$this, 'hydrateFromRecord'], $records);
    }

    /**
     * Update a dictionary.
     *
     * @param LocalDictionary $dictionary Dictionary entity
     *
     * @return bool Success
     */
    public function update(LocalDictionary $dictionary): bool
    {
        if ($dictionary->isNew()) {
            return false;
        }

        $data = [
            'name' => $dictionary->name(),
            'description' => $dictionary->description(),
            'priority' => $dictionary->priority(),
            'enabled' => $dictionary->isEnabled() ? 1 : 0,
            'entry_count' => $dictionary->entryCount(),
        ];

        return QueryBuilder::table('local_dictionaries')
            ->where('id', '=', $dictionary->id())
            ->updatePrepared($data) > 0;
    }

    /**
     * Delete a dictionary and all its entries.
     *
     * @param int $dictId Dictionary ID
     *
     * @return bool Success
     */
    public function delete(int $dictId): bool
    {
        // Entries are deleted via CASCADE
        return QueryBuilder::table('local_dictionaries')
            ->where('id', '=', $dictId)
            ->deletePrepared() > 0;
    }

    /**
     * Look up a term in local dictionaries for a language.
     *
     * @param int    $languageId Language ID
     * @param string $term       Term to look up
     *
     * @return array<array{term: string, definition: string, reading: ?string, pos: ?string, dictionary: string}>
     */
    public function lookup(int $languageId, string $term): array
    {
        $termLc = mb_strtolower(trim($term), 'UTF-8');

        $bindings = [$languageId, $termLc];
        $sql = "SELECT le.term, le.definition, le.reading, le.part_of_speech, ld.name
                FROM " . Globals::table('local_dictionary_entries') . " le
                INNER JOIN " . Globals::table('local_dictionaries') . " ld ON le.local_dictionary_id = ld.id
                WHERE ld.language_id = ? AND ld.enabled = 1 AND le.term_lc = ?"
                . UserScopedQuery::forTablePrepared('local_dictionaries', $bindings, 'ld')
                . " ORDER BY ld.priority ASC";

        $records = Connection::preparedFetchAll($sql, $bindings);

        return array_map(function ($row): array {
            return [
                'term' => (string) ($row['term'] ?? ''),
                'definition' => (string) ($row['definition'] ?? ''),
                'reading' => isset($row['reading']) ? (string) $row['reading'] : null,
                'pos' => isset($row['part_of_speech']) ? (string) $row['part_of_speech'] : null,
                'dictionary' => (string) ($row['name'] ?? ''),
            ];
        }, $records);
    }

    /**
     * Look up a term with prefix matching (for autocomplete).
     *
     * @param int    $languageId Language ID
     * @param string $prefix     Term prefix
     * @param int    $limit      Maximum results
     *
     * @return array<array{term: string, definition: string}>
     */
    public function lookupPrefix(int $languageId, string $prefix, int $limit = 10): array
    {
        $prefixLc = mb_strtolower(trim($prefix), 'UTF-8');

        $bindings = [$languageId, $prefixLc . '%'];
        $userScope = UserScopedQuery::forTablePrepared('local_dictionaries', $bindings, 'ld');
        $bindings[] = $limit;

        $sql = "SELECT DISTINCT le.term, le.definition
                FROM " . Globals::table('local_dictionary_entries') . " le
                INNER JOIN " . Globals::table('local_dictionaries') . " ld ON le.local_dictionary_id = ld.id
                WHERE ld.language_id = ? AND ld.enabled = 1 AND le.term_lc LIKE ?"
                . $userScope
                . " ORDER BY le.term_lc ASC
                LIMIT ?";

        $records = Connection::preparedFetchAll($sql, $bindings);

        return array_map(function ($row): array {
            return [
                'term' => (string) ($row['term'] ?? ''),
                'definition' => (string) ($row['definition'] ?? ''),
            ];
        }, $records);
    }

    /**
     * Add a single entry to a dictionary.
     *
     * @param int         $dictId     Dictionary ID
     * @param string      $term       Term/headword
     * @param string      $definition Definition
     * @param string|null $reading    Pronunciation/reading
     * @param string|null $pos        Part of speech
     *
     * @return int Entry ID
     */
    public function addEntry(
        int $dictId,
        string $term,
        string $definition,
        ?string $reading = null,
        ?string $pos = null
    ): int {
        $this->assertOwnsDictionary($dictId);

        $data = [
            'local_dictionary_id' => $dictId,
            'term' => $term,
            'term_lc' => mb_strtolower($term, 'UTF-8'),
            'definition' => $definition,
            'reading' => $reading,
            'part_of_speech' => $pos,
        ];

        QueryBuilder::table('local_dictionary_entries')->insertPrepared($data);
        return (int) Connection::lastInsertId();
    }

    /**
     * Add multiple entries to a dictionary in batches.
     *
     * @param int $dictId Dictionary ID
     * @param iterable<array{term: string, definition: string, reading?: ?string, pos?: ?string}> $entries
     *        Entries to add
     *
     * @return int Number of entries added
     */
    public function addEntriesBatch(int $dictId, iterable $entries): int
    {
        $this->assertOwnsDictionary($dictId);

        $batch = [];
        $count = 0;

        foreach ($entries as $entry) {
            $batch[] = [
                'local_dictionary_id' => $dictId,
                'term' => $entry['term'],
                'term_lc' => mb_strtolower($entry['term'], 'UTF-8'),
                'definition' => $entry['definition'],
                'reading' => $entry['reading'] ?? null,
                'part_of_speech' => $entry['pos'] ?? null,
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                $this->insertBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->insertBatch($batch);
            $count += count($batch);
        }

        // Update entry count
        $this->updateEntryCount($dictId);

        return $count;
    }

    /**
     * Delete all entries from a dictionary.
     *
     * @param int $dictId Dictionary ID
     *
     * @return int Number of entries deleted
     */
    public function clearEntries(int $dictId): int
    {
        $this->assertOwnsDictionary($dictId);

        $deleted = QueryBuilder::table('local_dictionary_entries')
            ->where('local_dictionary_id', '=', $dictId)
            ->deletePrepared();

        $this->updateEntryCount($dictId);

        return $deleted;
    }

    /**
     * Get entry count for a dictionary.
     *
     * @param int $dictId Dictionary ID
     *
     * @return int
     */
    public function getEntryCount(int $dictId): int
    {
        $this->assertOwnsDictionary($dictId);

        $result = QueryBuilder::table('local_dictionary_entries')
            ->select(['COUNT(*) as cnt'])
            ->where('local_dictionary_id', '=', $dictId)
            ->firstPrepared();

        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Get entries for a dictionary (paginated).
     *
     * @param int $dictId  Dictionary ID
     * @param int $page    Page number (1-based)
     * @param int $perPage Entries per page
     *
     * @return array{entries: array, total: int, page: int, perPage: int}
     */
    public function getEntries(int $dictId, int $page = 1, int $perPage = 50): array
    {
        $this->assertOwnsDictionary($dictId);

        $offset = max(0, ($page - 1) * $perPage);

        $total = $this->getEntryCount($dictId);

        $records = QueryBuilder::table('local_dictionary_entries')
            ->where('local_dictionary_id', '=', $dictId)
            ->orderBy('term_lc', 'ASC')
            ->limit($perPage)
            ->offset($offset)
            ->getPrepared();

        $entries = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'term' => $row['term'],
                'definition' => $row['definition'],
                'reading' => $row['reading'],
                'pos' => $row['part_of_speech'],
            ];
        }, $records);

        return [
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Update a single entry.
     *
     * @param int         $entryId    Entry ID
     * @param string      $term       Term
     * @param string      $definition Definition
     * @param string|null $reading    Reading
     * @param string|null $pos        Part of speech
     *
     * @return bool Success
     */
    public function updateEntry(
        int $entryId,
        string $term,
        string $definition,
        ?string $reading = null,
        ?string $pos = null
    ): bool {
        $entry = QueryBuilder::table('local_dictionary_entries')
            ->select(['local_dictionary_id'])
            ->where('id', '=', $entryId)
            ->firstPrepared();
        if ($entry === null) {
            return false;
        }
        $this->assertOwnsDictionary((int) $entry['local_dictionary_id']);

        $data = [
            'term' => $term,
            'term_lc' => mb_strtolower($term, 'UTF-8'),
            'definition' => $definition,
            'reading' => $reading,
            'part_of_speech' => $pos,
        ];

        return QueryBuilder::table('local_dictionary_entries')
            ->where('id', '=', $entryId)
            ->updatePrepared($data) > 0;
    }

    /**
     * Delete a single entry.
     *
     * @param int $entryId Entry ID
     *
     * @return bool Success
     */
    public function deleteEntry(int $entryId): bool
    {
        // Get dictionary ID first to update count
        $entry = QueryBuilder::table('local_dictionary_entries')
            ->select(['local_dictionary_id'])
            ->where('id', '=', $entryId)
            ->firstPrepared();

        if ($entry === null) {
            return false;
        }
        $this->assertOwnsDictionary((int) $entry['local_dictionary_id']);

        $deleted = QueryBuilder::table('local_dictionary_entries')
            ->where('id', '=', $entryId)
            ->deletePrepared() > 0;

        if ($deleted) {
            $this->updateEntryCount((int) $entry['local_dictionary_id']);
        }

        return $deleted;
    }

    /**
     * Check if a language has any local dictionaries.
     *
     * @param int $languageId Language ID
     *
     * @return bool
     */
    public function hasLocalDictionaries(int $languageId): bool
    {
        $result = QueryBuilder::table('local_dictionaries')
            ->select(['COUNT(*) as cnt'])
            ->where('language_id', '=', $languageId)
            ->where('enabled', '=', 1)
            ->firstPrepared();

        return ((int) ($result['cnt'] ?? 0)) > 0;
    }

    /**
     * Create vocabulary terms (status 1) from dictionary entries.
     *
     * Uses INSERT IGNORE to skip terms that already exist in the
     * vocabulary for this language. Sets status = 1 (new/unknown)
     * and translation from the dictionary definition.
     *
     * @param int $dictId     Dictionary ID
     * @param int $languageId Language ID
     *
     * @return int Number of vocabulary terms created
     */
    public function createVocabularyFromEntries(int $dictId, int $languageId): int
    {
        $this->assertOwnsDictionary($dictId);

        $entriesTable = Globals::table('local_dictionary_entries');
        $wordsTable = Globals::table('words');

        $bindings = [$languageId, $dictId];
        $userColumn = UserScopedQuery::insertColumn('words');
        $userValue = UserScopedQuery::insertValuePrepared('words', $bindings);

        // New learning terms (status 1) get FSRS column defaults: a new card
        // due now (issue #238). No legacy score columns.
        $sql = "INSERT IGNORE INTO {$wordsTable} (
                    language_id, text_lc, text, status, translation,
                    sentence, romanization, status_changed_at{$userColumn}
                )
                SELECT ?, le.term_lc, le.term, 1, le.definition,
                       '', '', NOW(){$userValue}
                FROM {$entriesTable} le
                WHERE le.local_dictionary_id = ?";

        return Connection::preparedExecute($sql, $bindings);
    }

    /**
     * Auto-enable local dictionary mode if currently set to online-only.
     *
     * When a dictionary is imported, if the language's local dict mode
     * is 0 (online only), upgrade it to 1 (local first, online fallback).
     *
     * @param int $languageId Language ID
     *
     * @return void
     */
    public function autoEnableLocalDictMode(int $languageId): void
    {
        $currentMode = $this->getLocalDictMode($languageId);
        if ($currentMode === 0) {
            QueryBuilder::table('languages')
                ->where('id', '=', $languageId)
                ->updatePrepared(['local_dict_mode' => 1]);
        }
    }

    /**
     * Get the local dictionary mode for a language.
     *
     * @param int $languageId Language ID
     *
     * @return int Mode (0=online only, 1=local first, 2=local only, 3=combined)
     */
    public function getLocalDictMode(int $languageId): int
    {
        $result = QueryBuilder::table('languages')
            ->select(['local_dict_mode'])
            ->where('id', '=', $languageId)
            ->firstPrepared();

        return (int) ($result['local_dict_mode'] ?? 0);
    }

    /**
     * Reject operations that target a dictionary the current user does not own.
     *
     * `local_dictionary_entries` is not auto-scoped (it has no user_id column),
     * so every entry-level call has to bounce through `getById` — which IS
     * user-scoped via QueryBuilder — to confirm ownership before touching the
     * row. Single-user mode is a no-op.
     *
     * @throws \RuntimeException When the dictionary is missing or belongs to another user.
     */
    private function assertOwnsDictionary(int $dictId): void
    {
        if (!Globals::isMultiUserEnabled()) {
            return;
        }
        if ($this->getById($dictId) === null) {
            throw new \RuntimeException("Dictionary $dictId not found or access denied");
        }
    }

    /**
     * Hydrate a LocalDictionary entity from a database record.
     *
     * @param array<string, mixed> $record Database record
     *
     * @return LocalDictionary
     */
    private function hydrateFromRecord(array $record): LocalDictionary
    {
        return LocalDictionary::reconstitute(
            (int) $record['id'],
            (int) $record['language_id'],
            (string) $record['name'],
            $record['description'] !== null ? (string) $record['description'] : null,
            (string) $record['source_format'],
            (int) $record['entry_count'],
            (int) $record['priority'],
            (bool) $record['enabled'],
            new DateTimeImmutable((string) $record['created_at']),
            $record['user_id'] !== null ? (int) $record['user_id'] : null
        );
    }

    /**
     * Insert a batch of entries.
     *
     * @param array<array<string, mixed>> $batch Batch of entry data
     *
     * @return void
     */
    private function insertBatch(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        $columns = ['local_dictionary_id', 'term', 'term_lc', 'definition', 'reading', 'part_of_speech'];
        $placeholders = [];
        $values = [];

        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?)';
            /** @var int $dictId */
            $dictId = $row['local_dictionary_id'];
            $values[] = $dictId;
            $values[] = (string) ($row['term'] ?? '');
            $values[] = (string) ($row['term_lc'] ?? '');
            $values[] = (string) ($row['definition'] ?? '');
            /** @var string|null $reading */
            $reading = $row['reading'] ?? null;
            $values[] = $reading;
            /** @var string|null $pos */
            $pos = $row['part_of_speech'] ?? null;
            $values[] = $pos;
        }

        $sql = "INSERT INTO " . Globals::table('local_dictionary_entries') .
               " (" . implode(', ', $columns) . ") VALUES " .
               implode(', ', $placeholders);

        Connection::preparedExecute($sql, $values);
    }

    /**
     * Update the entry count for a dictionary.
     *
     * @param int $dictId Dictionary ID
     *
     * @return void
     */
    private function updateEntryCount(int $dictId): void
    {
        $count = $this->getEntryCount($dictId);

        QueryBuilder::table('local_dictionaries')
            ->where('id', '=', $dictId)
            ->updatePrepared(['entry_count' => $count]);
    }
}
