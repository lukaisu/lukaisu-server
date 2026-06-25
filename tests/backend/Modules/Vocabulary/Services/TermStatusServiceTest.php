<?php

/**
 * Unit tests for TermStatusService.
 *
 * PHP version 8.1
 *
 * @category Testing
 * @package  Lukaisu\Tests\Modules\Vocabulary\Services
 * @license  Unlicense <http://unlicense.org/>
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\Services;

use Lukaisu\Modules\Vocabulary\Application\Services\TermStatusService;
use Lukaisu\Modules\Vocabulary\Domain\ValueObject\TermStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the TermStatusService.
 *
 * Covers status definitions, SQL fragment generation,
 * score calculations, and status classification helpers.
 *
 * @since 3.0.0
 */
class TermStatusServiceTest extends TestCase
{
    // =========================================================================
    // getStatuses()
    // =========================================================================

    #[Test]
    public function getStatusesReturnsAllSevenStatuses(): void
    {
        $statuses = TermStatusService::getStatuses();
        $this->assertCount(7, $statuses);
    }

    #[Test]
    public function getStatusesContainsExpectedKeys(): void
    {
        $statuses = TermStatusService::getStatuses();
        $expectedKeys = [1, 2, 3, 4, 5, 98, 99];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $statuses);
        }
    }

    #[Test]
    public function getStatusesEntriesHaveAbbrAndName(): void
    {
        $statuses = TermStatusService::getStatuses();
        foreach ($statuses as $status) {
            $this->assertArrayHasKey('abbr', $status);
            $this->assertArrayHasKey('name', $status);
            $this->assertIsString($status['abbr']);
            $this->assertIsString($status['name']);
        }
    }

    #[Test]
    public function getStatusesReturnsEqualResults(): void
    {
        $first = TermStatusService::getStatuses();
        $second = TermStatusService::getStatuses();
        $this->assertEquals($first, $second);
    }

    #[Test]
    public function getStatusesWellKnownHasNoAbbreviation(): void
    {
        // Status 99 no longer carries a hand-invented English abbreviation;
        // display code falls back to the localized full name.
        $statuses = TermStatusService::getStatuses();
        $this->assertSame('', $statuses[99]['abbr']);
        $this->assertSame('Well Known', $statuses[99]['name']);
    }

    #[Test]
    public function getStatusesIgnoredHasNoAbbreviation(): void
    {
        $statuses = TermStatusService::getStatuses();
        $this->assertSame('', $statuses[98]['abbr']);
        $this->assertSame('Ignored', $statuses[98]['name']);
    }

    // =========================================================================
    // makeScoreRandomInsertUpdate()
    // =========================================================================

    #[Test]
    public function makeScoreRandomInsertUpdateIvReturnsColumnNames(): void
    {
        // Issue #238: now seeds the FSRS scheduling columns, not the Leitner scores.
        $result = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $this->assertStringContainsString('stability', $result);
        $this->assertStringContainsString('due_at', $result);
        $this->assertStringContainsString('fsrs_state', $result);
    }

    #[Test]
    public function makeScoreRandomInsertUpdateIdReturnsSeedExpressions(): void
    {
        $result = TermStatusService::makeScoreRandomInsertUpdate('id');
        $this->assertStringContainsString('CASE', $result);
        $this->assertStringContainsString('status_changed_at', $result);
        $this->assertStringContainsString('INTERVAL', $result);
    }

    #[Test]
    public function makeScoreRandomInsertUpdateUReturnsSetClauses(): void
    {
        $result = TermStatusService::makeScoreRandomInsertUpdate('u');
        $this->assertStringContainsString('stability =', $result);
        $this->assertStringContainsString('due_at =', $result);
        $this->assertStringContainsString('fsrs_state =', $result);
    }

    #[Test]
    public function makeScoreRandomInsertUpdateUnknownTypeReturnsEmpty(): void
    {
        $result = TermStatusService::makeScoreRandomInsertUpdate('unknown');
        $this->assertSame('', $result);
    }

    // =========================================================================
    // isValidStatus()
    // =========================================================================

    #[Test]
    #[DataProvider('validStatusProvider')]
    public function isValidStatusReturnsTrueForValidStatuses(int $status): void
    {
        $this->assertTrue(TermStatusService::isValidStatus($status));
    }

    public static function validStatusProvider(): array
    {
        return [
            'new' => [1],
            'learning 2' => [2],
            'learning 3' => [3],
            'learning 4' => [4],
            'learned' => [5],
            'ignored' => [98],
            'well known' => [99],
        ];
    }

    #[Test]
    #[DataProvider('invalidStatusProvider')]
    public function isValidStatusReturnsFalseForInvalidStatuses(int $status): void
    {
        $this->assertFalse(TermStatusService::isValidStatus($status));
    }

    public static function invalidStatusProvider(): array
    {
        return [
            'zero' => [0],
            'six' => [6],
            'negative' => [-1],
            'fifty' => [50],
            'ninety seven' => [97],
            'one hundred' => [100],
        ];
    }

    // =========================================================================
    // getStatusName() / getStatusAbbr()
    // =========================================================================

    #[Test]
    public function getStatusNameReturnsCorrectNames(): void
    {
        $this->assertSame('Learning', TermStatusService::getStatusName(1));
        $this->assertSame('Learning', TermStatusService::getStatusName(2));
        $this->assertSame('Learned', TermStatusService::getStatusName(5));
        $this->assertSame('Well Known', TermStatusService::getStatusName(99));
        $this->assertSame('Ignored', TermStatusService::getStatusName(98));
    }

    #[Test]
    public function getStatusNameReturnsEmptyForInvalidStatus(): void
    {
        $this->assertSame('', TermStatusService::getStatusName(0));
        $this->assertSame('', TermStatusService::getStatusName(50));
    }

    #[Test]
    public function getStatusAbbrReturnsCorrectAbbreviations(): void
    {
        $this->assertSame('1', TermStatusService::getStatusAbbr(1));
        $this->assertSame('2', TermStatusService::getStatusAbbr(2));
        $this->assertSame('5', TermStatusService::getStatusAbbr(5));
        // Statuses 98 and 99 no longer have hand-invented English abbreviations.
        // Display code falls back to the localized full name when abbr is empty.
        $this->assertSame('', TermStatusService::getStatusAbbr(99));
        $this->assertSame('', TermStatusService::getStatusAbbr(98));
    }

    #[Test]
    public function getStatusAbbrReturnsEmptyForInvalidStatus(): void
    {
        $this->assertSame('', TermStatusService::getStatusAbbr(0));
    }

    // =========================================================================
    // getStatusColor()
    // =========================================================================

    #[Test]
    public function getStatusColorReturnsCorrectClasses(): void
    {
        $this->assertSame('status1', TermStatusService::getStatusColor(1));
        $this->assertSame('status2', TermStatusService::getStatusColor(2));
        $this->assertSame('status3', TermStatusService::getStatusColor(3));
        $this->assertSame('status4', TermStatusService::getStatusColor(4));
        $this->assertSame('status5', TermStatusService::getStatusColor(5));
        $this->assertSame('status98', TermStatusService::getStatusColor(98));
        $this->assertSame('status99', TermStatusService::getStatusColor(99));
    }

    #[Test]
    public function getStatusColorReturnsDefaultForUnknown(): void
    {
        $this->assertSame('status0', TermStatusService::getStatusColor(0));
        $this->assertSame('status0', TermStatusService::getStatusColor(50));
    }

    // =========================================================================
    // getLearningStatuses() / getActiveLearningStatuses() / getKnownStatuses()
    // =========================================================================

    #[Test]
    public function getLearningStatusesReturnsOneToFive(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], TermStatusService::getLearningStatuses());
    }

    #[Test]
    public function getActiveLearningStatusesReturnsOneToFour(): void
    {
        $this->assertSame([1, 2, 3, 4], TermStatusService::getActiveLearningStatuses());
    }

    #[Test]
    public function getKnownStatusesReturnsFiveAndNinetyNine(): void
    {
        $this->assertSame([5, 99], TermStatusService::getKnownStatuses());
    }

    // =========================================================================
    // isLearningStatus() / isKnownStatus() / isIgnoredStatus()
    // =========================================================================

    #[Test]
    public function isLearningStatusTrueForOneToFive(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue(
                TermStatusService::isLearningStatus($i),
                "Status $i should be learning"
            );
        }
    }

    #[Test]
    public function isLearningStatusFalseForSpecialStatuses(): void
    {
        $this->assertFalse(TermStatusService::isLearningStatus(0));
        $this->assertFalse(TermStatusService::isLearningStatus(6));
        $this->assertFalse(TermStatusService::isLearningStatus(98));
        $this->assertFalse(TermStatusService::isLearningStatus(99));
    }

    #[Test]
    public function isKnownStatusTrueForLearnedAndWellKnown(): void
    {
        $this->assertTrue(TermStatusService::isKnownStatus(5));
        $this->assertTrue(TermStatusService::isKnownStatus(99));
    }

    #[Test]
    public function isKnownStatusFalseForOthers(): void
    {
        $this->assertFalse(TermStatusService::isKnownStatus(1));
        $this->assertFalse(TermStatusService::isKnownStatus(4));
        $this->assertFalse(TermStatusService::isKnownStatus(98));
    }

    #[Test]
    public function isIgnoredStatusTrueForNinetyEight(): void
    {
        $this->assertTrue(TermStatusService::isIgnoredStatus(98));
    }

    #[Test]
    public function isIgnoredStatusFalseForOthers(): void
    {
        $this->assertFalse(TermStatusService::isIgnoredStatus(1));
        $this->assertFalse(TermStatusService::isIgnoredStatus(5));
        $this->assertFalse(TermStatusService::isIgnoredStatus(99));
    }
}
