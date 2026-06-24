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
        // Test 'iv' type - column names only
        $result = TermStatusService::makeScoreRandomInsertUpdate('iv');
        $this->assertStringContainsString('today_score', $result);
        $this->assertStringContainsString('tomorrow_score', $result);
        $this->assertStringContainsString('random', $result);
        $this->assertStringNotContainsString('=', $result);

        // Test 'id' type - values only (for INSERT)
        $result = TermStatusService::makeScoreRandomInsertUpdate('id');
        $this->assertStringContainsString('RAND()', $result);
        $this->assertStringContainsString('GREATEST', $result);
        $this->assertStringContainsString('status', $result);
        // Note: The result contains '=' in CASE conditions like "status = 1"
        // but not in assignment context (no "column = value")

        // Test 'u' type - key=value pairs for UPDATE
        $result = TermStatusService::makeScoreRandomInsertUpdate('u');
        $this->assertStringContainsString('today_score =', $result);
        $this->assertStringContainsString('tomorrow_score =', $result);
        $this->assertStringContainsString('random = RAND()', $result);
        $this->assertStringContainsString('GREATEST', $result);

        // Test default case (should return empty string)
        $result = TermStatusService::makeScoreRandomInsertUpdate('anything_else');
        $this->assertEquals('', $result);
    }
}
