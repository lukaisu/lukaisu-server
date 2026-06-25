<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Core\Word;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Vocabulary\Application\Services\TermStatusService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WordStatusService scoring functions
 */
final class WordScoringTest extends TestCase
{
    /**
     * Test makeScoreRandomInsertUpdate with different types
     */
    public function testMakeScoreRandomInsertUpdate(): void
    {
        // Test 'iv' type - FSRS column names only (no assignments)
        $result = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $this->assertStringContainsString('stability', $result);
        $this->assertStringContainsString('difficulty', $result);
        $this->assertStringContainsString('due_at', $result);
        $this->assertStringContainsString('fsrs_state', $result);
        $this->assertStringNotContainsString('=', $result);

        // Test 'id' type - FSRS seed values only (for INSERT), derived from status
        $result = TermStatusService::makeScoreRandomInsertUpdate('id');
        $this->assertStringContainsString('CASE', $result);
        $this->assertStringContainsString('status', $result);
        $this->assertStringNotContainsString('stability =', $result);

        // Test 'u' type - column = value pairs for UPDATE
        $result = TermStatusService::makeScoreRandomInsertUpdate('u');
        $this->assertStringContainsString('stability =', $result);
        $this->assertStringContainsString('difficulty =', $result);
        $this->assertStringContainsString('fsrs_state =', $result);
        $this->assertStringContainsString('lapses = 0', $result);

        // Test default case (should return empty string)
        $result = TermStatusService::makeScoreRandomInsertUpdate('anything_else');
        $this->assertEquals('', $result);
    }
}
