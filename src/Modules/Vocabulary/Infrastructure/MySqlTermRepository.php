<?php

/**
 * MySQL Term Repository
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Infrastructure;

use DateTimeImmutable;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Vocabulary\Domain\Term;
use Lukaisu\Modules\Vocabulary\Domain\TermRepositoryInterface;
use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermId;

/**
 * MySQL implementation of Term Repository.
 *
 * Provides database access for vocabulary/word management operations.
 * Handles both basic CRUD and term-specific queries.
 *
 * Query methods (find/search/pagination) are in TermQueryMethods trait.
 * Stats/update/bulk methods are in TermStatsMethods trait.
 *
 * @since 3.0.0
 */
class MySqlTermRepository implements TermRepositoryInterface
{
    use TermQueryMethods;
    use TermStatsMethods;

    /**
     * @var string Table name without prefix
     */
    protected string $tableName = 'words';

    /**
     * @var string Primary key column
     */
    protected string $primaryKey = 'id';

    /**
     * @var array<string, string> Property to column mapping
     */
    protected array $columnMap = [
        'id' => 'id',
        'languageId' => 'language_id',
        'text' => 'text',
        'textLowercase' => 'text_lc',
        'lemma' => 'lemma',
        'lemmaLc' => 'lemma_lc',
        'status' => 'status',
        'translation' => 'translation',
        'sentence' => 'sentence',
        'notes' => 'notes',
        'romanization' => 'romanization',
        'wordCount' => 'word_count',
        'createdAt' => 'created_at',
        'statusChangedAt' => 'status_changed_at',
    ];

    /**
     * Get a query builder for this repository's table.
     *
     * @return QueryBuilder
     */
    protected function query(): QueryBuilder
    {
        return QueryBuilder::table($this->tableName);
    }

    /**
     * Map a database row to a Term entity.
     *
     * @param array<string, mixed> $row Database row
     *
     * @return Term
     */
    protected function mapToEntity(array $row): Term
    {
        return Term::reconstitute(
            (int) $row['id'],
            (int) $row['language_id'],
            (string) $row['text'],
            (string) $row['text_lc'],
            isset($row['lemma']) && $row['lemma'] !== '' ? (string) $row['lemma'] : null,
            isset($row['lemma_lc']) && $row['lemma_lc'] !== '' ? (string) $row['lemma_lc'] : null,
            (int) $row['status'],
            (string) ($row['translation'] ?? ''),
            (string) ($row['sentence'] ?? ''),
            (string) ($row['notes'] ?? ''),
            (string) ($row['romanization'] ?? ''),
            (int) ($row['word_count'] ?? 1),
            $this->parseDateTime(isset($row['created_at']) ? (string)$row['created_at'] : null),
            $this->parseDateTime(isset($row['status_changed_at']) ? (string)$row['status_changed_at'] : null)
        );
    }

    /**
     * Map a Term entity to a database row.
     *
     * @param Term $term The term entity
     *
     * @return array<string, null|scalar> Database column => value pairs
     */
    protected function mapToRow(Term $term): array
    {
        return [
            'id' => $term->id()->toInt(),
            'language_id' => $term->languageId()->toInt(),
            'text' => $term->text(),
            'text_lc' => $term->textLowercase(),
            'lemma' => $term->lemma(),
            'lemma_lc' => $term->lemmaLc(),
            'status' => $term->status()->toInt(),
            'translation' => $term->translation(),
            'sentence' => $term->sentence(),
            'notes' => $term->notes(),
            'romanization' => $term->romanization(),
            'word_count' => $term->wordCount(),
            'created_at' => $term->createdAt()->format('Y-m-d H:i:s'),
            'status_changed_at' => $term->statusChangedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse a datetime string into DateTimeImmutable.
     *
     * @param string|null $datetime The datetime string
     *
     * @return DateTimeImmutable
     */
    private function parseDateTime(?string $datetime): DateTimeImmutable
    {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return new DateTimeImmutable();
        }
        return new DateTimeImmutable($datetime);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Term
    {
        $row = $this->query()
            ->where($this->primaryKey, '=', $id)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        $rows = $this->query()->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function save(Term $term): int
    {
        if ($term->id()->isNew()) {
            // Insert new term
            $data = $this->mapToRow($term);
            unset($data['id']); // Remove ID for insert

            $id = (int) $this->query()->insertPrepared($data);
            $term->setId(TermId::fromInt($id));
            return $id;
        } else {
            // Update existing term
            $data = $this->mapToRow($term);
            unset($data['id']);

            $this->query()
                ->where('id', '=', $term->id()->toInt())
                ->updatePrepared($data);
            return $term->id()->toInt();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $affected = $this->query()
            ->where('id', '=', $id)
            ->deletePrepared();

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(int $id): bool
    {
        return $this->query()
            ->where('id', '=', $id)
            ->existsPrepared();
    }

    /**
     * Count terms matching criteria.
     *
     * @param array<string, mixed> $criteria Field => value pairs
     *
     * @return int The count
     */
    public function count(array $criteria = []): int
    {
        $query = $this->query();

        /**
         * @var string $field
         * @var mixed $value
         */
        foreach ($criteria as $field => $value) {
            $column = $this->columnMap[$field] ?? $field;
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } elseif ($value === null) {
                $query->whereNull($column);
            } elseif (is_scalar($value)) {
                $query->where($column, '=', $value);
            }
        }

        return $query->countPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function findByLanguage(
        int $languageId,
        ?string $orderBy = 'text',
        string $direction = 'ASC'
    ): array {
        $rows = $this->query()
            ->where('language_id', '=', $languageId)
            ->orderBy($orderBy ?? 'text', $direction)
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findByTextLc(int $languageId, string $textLc): ?Term
    {
        $row = $this->query()
            ->where('language_id', '=', $languageId)
            ->where('text_lc', '=', $textLc)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function termExists(int $languageId, string $textLc, ?int $excludeId = null): bool
    {
        $query = $this->query()
            ->where('language_id', '=', $languageId)
            ->where('text_lc', '=', $textLc);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function countByLanguage(int $languageId): int
    {
        return $this->query()
            ->where('language_id', '=', $languageId)
            ->countPrepared();
    }
}
