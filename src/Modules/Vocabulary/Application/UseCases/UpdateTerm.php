<?php

/**
 * Update Term Use Case
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

use Lukaisu\Modules\Vocabulary\Domain\Term;
use Lukaisu\Modules\Vocabulary\Domain\TermRepositoryInterface;
use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermStatus;

/**
 * Use case for updating an existing term.
 */
class UpdateTerm
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
     * Execute the update term use case.
     *
     * @param int         $termId       Term ID to update
     * @param int|null    $status       New status (null to keep current)
     * @param string|null $translation  New translation (null to keep current)
     * @param string|null $sentence     New sentence (null to keep current)
     * @param string|null $notes        New notes (null to keep current)
     * @param string|null $romanization New romanization (null to keep current)
     *
     * @return Term The updated term entity
     *
     * @throws \InvalidArgumentException If term not found
     */
    public function execute(
        int $termId,
        ?int $status = null,
        ?string $translation = null,
        ?string $sentence = null,
        ?string $notes = null,
        ?string $romanization = null
    ): Term {
        $term = $this->repository->find($termId);

        if ($term === null) {
            throw new \InvalidArgumentException('Term not found: ' . $termId);
        }

        // Update fields if provided
        if ($status !== null) {
            $term->setStatus(TermStatus::fromInt($status));
        }

        if ($translation !== null) {
            $term->updateTranslation($this->normalizeTranslation($translation));
        }

        if ($sentence !== null) {
            $term->updateSentence($this->replaceTabNewline($sentence));
        }

        if ($notes !== null) {
            $term->updateNotes($this->replaceTabNewline($notes));
        }

        if ($romanization !== null) {
            $term->updateRomanization($romanization);
        }

        // Persist changes
        $this->repository->save($term);

        return $term;
    }

    /**
     * Execute and return result array (backward compatible with WordService).
     *
     * @param array $data Update data array with keys like id, status, etc.
     *
     * @return array{id: int, message: string, success: bool}
     */
    public function executeFromArray(array $data): array
    {
        try {
            $termId = (int) ($data['id'] ?? 0);

            $term = $this->execute(
                $termId,
                isset($data['status']) ? (int) $data['status'] : null,
                isset($data['translation']) ? (string)$data['translation'] : null,
                isset($data['sentence']) ? (string)$data['sentence'] : null,
                isset($data['notes']) ? (string)$data['notes'] : null,
                isset($data['romanization']) ? (string)$data['romanization'] : null
            );

            return [
                'id' => $term->id()->toInt(),
                'message' => __('vocabulary.flash.term_updated'),
                'success' => true
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'id' => 0,
                'message' => __('vocabulary.flash.error_prefix', ['message' => $e->getMessage()]),
                'success' => false
            ];
        } catch (\Exception $e) {
            return [
                'id' => 0,
                'message' => __('vocabulary.flash.error_prefix', ['message' => $e->getMessage()]),
                'success' => false
            ];
        }
    }

    /**
     * Normalize translation text.
     *
     * @param string $translation Raw translation
     *
     * @return string Normalized translation
     */
    private function normalizeTranslation(string $translation): string
    {
        $trans = trim($translation);
        if ($trans === '' || $trans === '*') {
            return '*';
        }
        return $trans;
    }

    /**
     * Replace tabs and newlines in text.
     *
     * @param string $text Input text
     *
     * @return string Cleaned text
     */
    private function replaceTabNewline(string $text): string
    {
        return str_replace(["\t", "\r\n", "\n", "\r"], ' ', $text);
    }
}
