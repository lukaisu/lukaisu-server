<?php

/**
 * Get Term By ID Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Application\UseCases;

use Lukaisu\Modules\Vocabulary\Domain\Term;
use Lukaisu\Modules\Vocabulary\Domain\TermRepositoryInterface;

/**
 * Use case for retrieving a term by its ID.
 *
 * @since 3.0.0
 */
class GetTermById
{
    private TermRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param TermRepositoryInterface $repository Term repository
     */
    public function __construct(TermRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the get term use case.
     *
     * @param int $termId Term ID
     *
     * @return Term|null The term entity or null if not found
     */
    public function execute(int $termId): ?Term
    {
        if ($termId <= 0) {
            return null;
        }

        return $this->repository->find($termId);
    }

    /**
     * Execute and return array format (backward compatible).
     *
     * @param int $termId Term ID
     *
     * @return array|null Term data array or null if not found
     */
    public function executeAsArray(int $termId): ?array
    {
        $term = $this->execute($termId);

        if ($term === null) {
            return null;
        }

        return [
            'id' => $term->id()->toInt(),
            'language_id' => $term->languageId()->toInt(),
            'text' => $term->text(),
            'text_lc' => $term->textLowercase(),
            'status' => $term->status()->toInt(),
            'translation' => $term->translation(),
            'sentence' => $term->sentence(),
            'notes' => $term->notes(),
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
