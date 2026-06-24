<?php

/**
 * Term Status Value Object
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Vocabulary\Domain\ValueObject
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Vocabulary\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value object representing a Term's learning status.
 *
 * Encapsulates the business rules around term status transitions.
 *
 * Status values:
 * - 1-5: Learning stages (1=new, 5=learned)
 * - 98: Ignored words
 * - 99: Well-known words
 *
 * @since 3.0.0
 */
final readonly class TermStatus
{
    /** @var int Unknown term (status 0) — a display-only value, never stored. */
    public const UNKNOWN = 0;

    /** @var int New/unknown term */
    public const NEW = 1;

    /** @var int Learning stage 2 */
    public const LEARNING_2 = 2;

    /** @var int Learning stage 3 */
    public const LEARNING_3 = 3;

    /** @var int Learning stage 4 */
    public const LEARNING_4 = 4;

    /** @var int Fully learned */
    public const LEARNED = 5;

    /** @var int Ignored term */
    public const IGNORED = 98;

    /** @var int Well-known term (no need to learn) */
    public const WELL_KNOWN = 99;

    /** @var int[] Valid stored status values (0 / "unknown" is display-only). */
    private const VALID_STATUSES = [
        self::NEW,
        self::LEARNING_2,
        self::LEARNING_3,
        self::LEARNING_4,
        self::LEARNED,
        self::IGNORED,
        self::WELL_KNOWN,
    ];

    /**
     * The authoritative display model for every status — the single source of
     * truth the API exposes (`GET /api/v1/statuses`) and the frontend mirrors in
     * `shared/stores/statuses.ts`. Ordered by `order` for list rendering.
     *
     * `label` is the English display name; localized names for the reading view
     * still flow through {@see TermStatusService::getStatuses()} / i18n. Colours
     * are the canonical reading-view palette (the admin statistics charts keep a
     * deliberately distinct pastel palette).
     *
     * @var array<int, array{abbr: string, label: string, cssClass: string, colourHex: string, order: int}>
     */
    private const DISPLAY = [
        self::UNKNOWN => ['abbr' => '', 'label' => 'Unknown', 'cssClass' => 'status0',
            'colourHex' => '#8bdadc', 'order' => 0],
        self::NEW => ['abbr' => '1', 'label' => 'Learning (1)', 'cssClass' => 'status1',
            'colourHex' => '#f24e4e', 'order' => 1],
        self::LEARNING_2 => ['abbr' => '2', 'label' => 'Learning (2)', 'cssClass' => 'status2',
            'colourHex' => '#ffac80', 'order' => 2],
        self::LEARNING_3 => ['abbr' => '3', 'label' => 'Learning (3)', 'cssClass' => 'status3',
            'colourHex' => '#ffe199', 'order' => 3],
        self::LEARNING_4 => ['abbr' => '4', 'label' => 'Learning (4)', 'cssClass' => 'status4',
            'colourHex' => '#fffd77', 'order' => 4],
        self::LEARNED => ['abbr' => '5', 'label' => 'Learned (5)', 'cssClass' => 'status5',
            'colourHex' => '#99ff99', 'order' => 5],
        self::WELL_KNOWN => ['abbr' => 'Known', 'label' => 'Well Known', 'cssClass' => 'status99',
            'colourHex' => '#999999', 'order' => 6],
        self::IGNORED => ['abbr' => 'Ignore', 'label' => 'Ignored', 'cssClass' => 'status98',
            'colourHex' => '#aaaaaa', 'order' => 7],
    ];

    /**
     * @param int $value The status value
     */
    private function __construct(private int $value)
    {
    }

    /**
     * Create from a database value.
     *
     * @param int $status The status value from database
     *
     * @return self
     *
     * @throws InvalidArgumentException If status is invalid
     */
    public static function fromInt(int $status): self
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                'Invalid term status: ' . $status . '. Valid values: ' . implode(', ', self::VALID_STATUSES)
            );
        }
        return new self($status);
    }

    /**
     * Create a new (unknown) status.
     *
     * @return self
     */
    public static function new(): self
    {
        return new self(self::NEW);
    }

    /**
     * Create a learned status.
     *
     * @return self
     */
    public static function learned(): self
    {
        return new self(self::LEARNED);
    }

    /**
     * Create an ignored status.
     *
     * @return self
     */
    public static function ignored(): self
    {
        return new self(self::IGNORED);
    }

    /**
     * Create a well-known status.
     *
     * @return self
     */
    public static function wellKnown(): self
    {
        return new self(self::WELL_KNOWN);
    }

    /**
     * Advance to the next learning stage.
     *
     * Returns a new TermStatus with the next stage, or the same status
     * if already at maximum learning level or special status.
     *
     * @return self
     */
    public function advance(): self
    {
        if ($this->value >= self::LEARNED || $this->isSpecial()) {
            return $this;
        }
        return new self($this->value + 1);
    }

    /**
     * Decrease to the previous learning stage.
     *
     * Returns a new TermStatus with the previous stage, or the same status
     * if already at minimum level or special status.
     *
     * @return self
     */
    public function decrease(): self
    {
        if ($this->value <= self::NEW || $this->isSpecial()) {
            return $this;
        }
        return new self($this->value - 1);
    }

    /**
     * Check if the term is known (learned or well-known).
     *
     * @return bool
     */
    public function isKnown(): bool
    {
        return $this->value === self::LEARNED || $this->value === self::WELL_KNOWN;
    }

    /**
     * Check if the term is in a learning stage (1-4).
     *
     * @return bool
     */
    public function isLearning(): bool
    {
        return $this->value >= self::NEW && $this->value <= self::LEARNING_4;
    }

    /**
     * Check if this is a special status (ignored or well-known).
     *
     * @return bool
     */
    public function isSpecial(): bool
    {
        return $this->value === self::IGNORED || $this->value === self::WELL_KNOWN;
    }

    /**
     * Check if the term is ignored.
     *
     * @return bool
     */
    public function isIgnored(): bool
    {
        return $this->value === self::IGNORED;
    }

    /**
     * Check if the term needs review (learning stages 1-4).
     *
     * @return bool
     */
    public function needsReview(): bool
    {
        return $this->isLearning();
    }

    /**
     * Get the integer value.
     *
     * @return int
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Check equality with another TermStatus.
     *
     * @param TermStatus $other The other status to compare
     *
     * @return bool
     */
    public function equals(TermStatus $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get a human-readable label.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this->value) {
            self::NEW => 'New',
            self::LEARNING_2 => 'Learning (2)',
            self::LEARNING_3 => 'Learning (3)',
            self::LEARNING_4 => 'Learning (4)',
            self::LEARNED => 'Learned',
            self::IGNORED => 'Ignored',
            self::WELL_KNOWN => 'Well Known',
            default => 'Unknown',
        };
    }

    /**
     * Language-neutral abbreviation ("1".."5" for learning, "Known"/"Ignore").
     *
     * @return string
     */
    public function abbreviation(): string
    {
        return self::DISPLAY[$this->value]['abbr'];
    }

    /**
     * Reading-view CSS class for this status (e.g. "status1", "status99").
     *
     * @return string
     */
    public function cssClass(): string
    {
        return self::DISPLAY[$this->value]['cssClass'];
    }

    /**
     * Canonical reading-view colour as a hex string (e.g. "#f24e4e").
     *
     * @return string
     */
    public function colourHex(): string
    {
        return self::DISPLAY[$this->value]['colourHex'];
    }

    /**
     * Display/sort order across all statuses (learning ascending, then 99, 98).
     *
     * @return int
     */
    public function order(): int
    {
        return self::DISPLAY[$this->value]['order'];
    }

    /**
     * Whether an integer is a valid stored term status (1-5, 98, 99).
     *
     * The single source of truth for the `[1,2,3,4,5,98,99]` checks that were
     * scattered across the codebase.
     *
     * @param int $status The status value to validate
     *
     * @return bool
     */
    public static function isValid(int $status): bool
    {
        return in_array($status, self::VALID_STATUSES, true);
    }

    /**
     * All valid stored status values (1-5, 98, 99).
     *
     * @return int[]
     */
    public static function all(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * The complete display model, ordered, including the display-only status 0.
     *
     * This is what `GET /api/v1/statuses` exposes and what the frontend store
     * mirrors. Each entry is self-describing so no consumer needs to re-derive
     * labels, colours, classes, ordering, or predicates.
     *
     * @return list<array{
     *     value: int, label: string, abbr: string, cssClass: string,
     *     colourHex: string, order: int, isKnown: bool, isLearning: bool,
     *     isIgnored: bool
     * }>
     */
    public static function definitions(): array
    {
        $out = [];
        foreach (self::DISPLAY as $value => $d) {
            $out[] = [
                'value'      => $value,
                'label'      => $d['label'],
                'abbr'       => $d['abbr'],
                'cssClass'   => $d['cssClass'],
                'colourHex'  => $d['colourHex'],
                'order'      => $d['order'],
                'isKnown'    => $value === self::LEARNED || $value === self::WELL_KNOWN,
                'isLearning' => $value >= self::NEW && $value <= self::LEARNING_4,
                'isIgnored'  => $value === self::IGNORED,
            ];
        }
        return $out;
    }

    /**
     * String representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}
