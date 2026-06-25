<?php

/**
 * Review Word Entity
 *
 * Represents a word being reviewed with its context.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Review\Domain
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Review\Domain;

/**
 * Entity representing a word being reviewed.
 *
 * Contains all word data needed for the test interface including
 * the word itself, translation, sentence context, and scoring info.
 *
 * @since 3.0.0
 */
final class ReviewWord
{
    /**
     * Constructor.
     *
     * @param int         $id            Word ID
     * @param string      $text          Word text
     * @param string      $textLowercase Lowercase word text
     * @param string      $translation   Translation
     * @param string|null $romanization  Romanization (optional)
     * @param string|null $sentence      Sentence context (optional)
     * @param int         $languageId    Language ID
     * @param int         $status        Current status (1-5, 98, 99)
     * @param int         $score         Today's score
     * @param int         $daysOld       Days since status changed
     * @param array{
     *     stability: float,
     *     difficulty: float,
     *     due: int,
     *     lastReview: int|null,
     *     reps: int,
     *     lapses: int,
     *     state: int
     * }|null $fsrs FSRS scheduling card (epoch-ms timestamps), or null when not loaded
     */
    public function __construct(
        public readonly int $id,
        public readonly string $text,
        public readonly string $textLowercase,
        public readonly string $translation,
        public readonly ?string $romanization,
        public readonly ?string $sentence,
        public readonly int $languageId,
        public readonly int $status,
        public readonly int $score,
        public readonly int $daysOld,
        public readonly ?array $fsrs = null
    ) {
    }

    /**
     * Create from database record.
     *
     * @param array<string, mixed> $record Database record
     *
     * @return self
     */
    public static function fromRecord(array $record): self
    {
        /** @var mixed $romanization */
        $romanization = $record['romanization'] ?? null;
        /** @var mixed $sentence */
        $sentence = $record['sentence'] ?? null;

        return new self(
            (int) $record['id'],
            (string) $record['text'],
            (string) $record['text_lc'],
            (string) $record['translation'],
            is_string($romanization) ? $romanization : null,
            is_string($sentence) ? $sentence : null,
            (int) $record['language_id'],
            (int) $record['status'],
            (int) ($record['Score'] ?? $record['today_score'] ?? 0),
            (int) ($record['Days'] ?? 0),
            self::fsrsFromRecord($record)
        );
    }

    /**
     * Build the FSRS card from a record (issue #238). The query exposes the
     * `due_at`/`last_reviewed_at` datetimes as epoch-second columns
     * (`due_ts`/`last_review_ts`) so they round-trip with the client's epoch-ms
     * `ReviewCard`. Returns null when the FSRS columns weren't selected.
     *
     * @param array<string, mixed> $record Database record
     *
     * @return array{
     *     stability: float,
     *     difficulty: float,
     *     due: int,
     *     lastReview: int|null,
     *     reps: int,
     *     lapses: int,
     *     state: int
     * }|null
     */
    private static function fsrsFromRecord(array $record): ?array
    {
        if (!array_key_exists('due_ts', $record)) {
            return null;
        }

        /** @var mixed $lastReviewTs */
        $lastReviewTs = $record['last_review_ts'] ?? null;

        return [
            'stability' => (float) ($record['stability'] ?? 0),
            'difficulty' => (float) ($record['difficulty'] ?? 0),
            'due' => (int) round(((float) ($record['due_ts'] ?? 0)) * 1000),
            'lastReview' => $lastReviewTs !== null
                ? (int) round(((float) $lastReviewTs) * 1000)
                : null,
            'reps' => (int) ($record['reps'] ?? 0),
            'lapses' => (int) ($record['lapses'] ?? 0),
            'state' => (int) ($record['fsrs_state'] ?? 0),
        ];
    }

    /**
     * Check if word has a sentence context.
     *
     * @psalm-assert-if-true non-empty-string $this->sentence
     *
     * @return bool
     */
    public function hasSentence(): bool
    {
        return $this->sentence !== null && $this->sentence !== '';
    }

    /**
     * Check if the stored sentence needs updating.
     *
     * A sentence needs updating if it doesn't contain the word
     * marked with curly braces.
     *
     * @return bool True if sentence is invalid/missing
     */
    public function needsNewSentence(): bool
    {
        if (!$this->hasSentence()) {
            return true;
        }
        return strpos($this->sentence, '{' . $this->text . '}') === false;
    }

    /**
     * Get sentence for display (with word marked).
     *
     * If no sentence exists, returns the word wrapped in braces.
     *
     * @return string Sentence with word marked
     */
    public function getSentenceForDisplay(): string
    {
        if ($this->hasSentence()) {
            return $this->sentence;
        }
        return '{' . $this->text . '}';
    }

    /**
     * Check if word is in learning status (1-5).
     *
     * @return bool
     */
    public function isLearning(): bool
    {
        return $this->status >= 1 && $this->status <= 5;
    }

    /**
     * Check if word is well-known (status 99).
     *
     * @return bool
     */
    public function isWellKnown(): bool
    {
        return $this->status === 99;
    }

    /**
     * Check if word is ignored (status 98).
     *
     * @return bool
     */
    public function isIgnored(): bool
    {
        return $this->status === 98;
    }

    /**
     * Convert to array for JSON response.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'textLowercase' => $this->textLowercase,
            'translation' => $this->translation,
            'romanization' => $this->romanization,
            'sentence' => $this->sentence,
            'languageId' => $this->languageId,
            'status' => $this->status,
            'score' => $this->score,
            'daysOld' => $this->daysOld,
            'fsrs' => $this->fsrs
        ];
    }
}
