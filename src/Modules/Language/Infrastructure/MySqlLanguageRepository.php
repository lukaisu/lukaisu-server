<?php

/**
 * MySQL Language Repository
 *
 * Infrastructure adapter for language persistence using MySQL.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Language\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Language\Infrastructure;

use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\Language\Domain\Language;
use Lukaisu\Modules\Language\Domain\LanguageRepositoryInterface;
use Lukaisu\Modules\Language\Domain\ValueObject\LanguageId;

/**
 * MySQL implementation of LanguageRepositoryInterface.
 *
 * Provides database access for language management operations.
 *
 * @since 3.0.0
 */
class MySqlLanguageRepository implements LanguageRepositoryInterface
{
    /**
     * @var string Table name without prefix
     */
    private string $tableName = 'languages';

    /**
     * @var string Primary key column
     */
    private string $primaryKey = 'id';

    /**
     * @var array<string, string> Property to column mapping
     */
    private array $columnMap = [
        'id' => 'id',
        'name' => 'name',
        'dict1uri' => 'dict1_uri',
        'dict2uri' => 'dict2_uri',
        'translator' => 'google_translate_uri',
        'dict1popup' => 'dict1_popup',
        'dict2popup' => 'dict2_popup',
        'translatorpopup' => 'google_translate_popup',
        'sourcelang' => 'source_lang',
        'targetlang' => 'target_lang',
        'exporttemplate' => 'export_template',
        'textsize' => 'text_size',
        'charactersubst' => 'character_substitutions',
        'regexpsplitsent' => 'regexp_split_sentences',
        'exceptionsplitsent' => 'exceptions_split_sentences',
        'regexpwordchar' => 'regexp_word_characters',
        'parsertype' => 'parser_type',
        'removespaces' => 'remove_spaces',
        'spliteachchar' => 'split_each_char',
        'rightoleft' => 'right_to_left',
        'ttsvoiceapi' => 'tts_voice_api',
        'showromanization' => 'show_romanization',
        'localdictmode' => 'local_dict_mode',
    ];

    /**
     * Get a query builder for this repository's table.
     *
     * @return QueryBuilder
     */
    private function query(): QueryBuilder
    {
        return QueryBuilder::table($this->tableName);
    }

    /**
     * Map a database row to a Language entity.
     *
     * @param array<string, mixed> $row Database row
     *
     * @return Language
     */
    private function mapToEntity(array $row): Language
    {
        return Language::reconstitute(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['dict1_uri'],
            (string) ($row['dict2_uri'] ?? ''),
            (string) ($row['google_translate_uri'] ?? ''),
            (bool) ($row['dict1_popup'] ?? false),
            (bool) ($row['dict2_popup'] ?? false),
            (bool) ($row['google_translate_popup'] ?? false),
            isset($row['source_lang']) && $row['source_lang'] !== '' ? (string) $row['source_lang'] : null,
            isset($row['target_lang']) && $row['target_lang'] !== '' ? (string) $row['target_lang'] : null,
            (string) ($row['export_template'] ?? ''),
            (int) ($row['text_size'] ?? 100),
            (string) ($row['character_substitutions'] ?? ''),
            (string) $row['regexp_split_sentences'],
            (string) ($row['exceptions_split_sentences'] ?? ''),
            (string) $row['regexp_word_characters'],
            (bool) ($row['remove_spaces'] ?? false),
            (bool) ($row['split_each_char'] ?? false),
            (bool) ($row['right_to_left'] ?? false),
            (string) ($row['tts_voice_api'] ?? ''),
            (bool) ($row['show_romanization'] ?? false),
            isset($row['parser_type']) ? (string) $row['parser_type'] : null,
            (int) ($row['local_dict_mode'] ?? 0)
        );
    }

    /**
     * Map a Language entity to database row.
     *
     * @param Language $entity The language entity
     *
     * @return array<string, scalar|null>
     */
    private function mapToRow(Language $entity): array
    {
        return [
            'id' => $entity->id()->toInt(),
            'name' => $entity->name(),
            'dict1_uri' => $entity->dict1Uri(),
            'dict2_uri' => $entity->dict2Uri(),
            'google_translate_uri' => $entity->translatorUri(),
            'export_template' => $entity->exportTemplate(),
            'text_size' => $entity->textSize(),
            'character_substitutions' => $entity->characterSubstitutions(),
            'regexp_split_sentences' => $entity->regexpSplitSentences(),
            'exceptions_split_sentences' => $entity->exceptionsSplitSentences(),
            'regexp_word_characters' => $entity->regexpWordCharacters(),
            'parser_type' => $entity->parserType(),
            'remove_spaces' => (int) $entity->removeSpaces(),
            'split_each_char' => (int) $entity->splitEachChar(),
            'right_to_left' => (int) $entity->rightToLeft(),
            'tts_voice_api' => $entity->ttsVoiceApi(),
            'show_romanization' => (int) $entity->showRomanization(),
            'local_dict_mode' => $entity->localDictMode(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Language
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
    public function save(Language $entity): void
    {
        $data = $this->mapToRow($entity);
        $id = $entity->id()->toInt();

        if ($id > 0 && !$entity->id()->isNew()) {
            // Update existing
            $query = $this->query()->where($this->primaryKey, '=', $id);
            $query->updatePrepared($data);
            return;
        }

        // Insert new
        $insertData = $data;
        unset($insertData[$this->primaryKey]); // Remove ID for auto-increment

        $newId = (int) $this->query()->insertPrepared($insertData);
        $entity->setId(LanguageId::fromInt($newId));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $this->query()
            ->where($this->primaryKey, '=', $id)
            ->deletePrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(int $id): bool
    {
        return $this->query()
            ->where($this->primaryKey, '=', $id)
            ->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllActive(string $orderBy = 'name', string $direction = 'ASC'): array
    {
        $rows = $this->query()
            ->where('name', '!=', '')
            ->orderBy($orderBy, $direction)
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(string $name): ?Language
    {
        $row = $this->query()
            ->where('name', '=', $name)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $query = $this->query()->where('name', '=', $name);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllAsDict(): array
    {
        $languages = [];
        $rows = $this->query()
            ->select(['id', 'name'])
            ->where('name', '!=', '')
            ->getPrepared();

        foreach ($rows as $row) {
            $languages[(string) $row['name']] = (int) $row['id'];
        }

        return $languages;
    }

    /**
     * {@inheritdoc}
     */
    public function getForSelect(int $maxNameLength = 30): array
    {
        $result = [];
        $rows = $this->query()
            ->select(['id', 'name'])
            ->where('name', '!=', '')
            ->orderBy('name')
            ->getPrepared();

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            if (strlen($name) > $maxNameLength) {
                $name = substr($name, 0, $maxNameLength) . '...';
            }
            $result[] = [
                'id' => (int) $row['id'],
                'name' => $name,
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findEmptyLanguageId(): ?int
    {
        $row = $this->query()
            ->select('id')
            ->where('name', '=', '')
            ->orderBy('id')
            ->limit(1)
            ->firstPrepared();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function isRightToLeft(int $id): bool
    {
        $row = $this->query()
            ->select('right_to_left')
            ->where('id', '=', $id)
            ->firstPrepared();

        return $row !== null && (bool) $row['right_to_left'];
    }

    /**
     * {@inheritdoc}
     */
    public function getWordCharacters(int $id): ?string
    {
        $row = $this->query()
            ->select('regexp_word_characters')
            ->where('id', '=', $id)
            ->firstPrepared();

        return $row !== null ? (string) $row['regexp_word_characters'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function createEmpty(): Language
    {
        return Language::create(
            'New Language',
            '',
            '.!?',
            'a-zA-Z'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(int $id): ?string
    {
        $row = $this->query()
            ->select('name')
            ->where('id', '=', $id)
            ->firstPrepared();

        return $row !== null ? (string) $row['name'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslatorUri(int $id): ?string
    {
        $row = $this->query()
            ->select('google_translate_uri')
            ->where('id', '=', $id)
            ->firstPrepared();

        return $row !== null ? (string) $row['google_translate_uri'] : null;
    }
}
