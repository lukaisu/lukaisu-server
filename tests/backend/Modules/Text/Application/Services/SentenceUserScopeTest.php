<?php

/**
 * Source-inspection tests for `SentenceService` user-scoping (F14).
 *
 * `findSentencesFromWord` and `formatSentence` back the
 * `GET /api/v1/sentences-with-term` endpoint. Both join `sentences` and
 * `word_occurrences` raw — neither table carries a user_id column of its
 * own. Without an explicit parent-text scope, an authenticated user
 * could request sentences (or sentence content) belonging to another
 * user simply by passing that user's `language_id` / id.
 *
 * The fix scopes via the parent `texts.user_id` column: `find*` rewrites
 * its WHERE to `AND text_id IN (SELECT id FROM texts WHERE user_id = ?)`,
 * and `formatSentence` short-circuits when `ownsSentence()` reports the
 * id's parent text isn't owned by the caller. This test reads each
 * method's source via reflection and asserts those gates are present.
 *
 * PHP version 8.1
 *
 * @category Testing
 * @package  Lukaisu\Tests\Modules\Text\Application\Services
 * @license  Unlicense <http://unlicense.org/>
 * @since    3.1.2-fork
 */

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Text\Application\Services;

use Lukaisu\Modules\Text\Application\Services\SentenceService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @since 3.1.2-fork
 */
class SentenceUserScopeTest extends TestCase
{
    private function getMethodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $file = $reflection->getFileName();
        $this->assertIsString($file);
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();
        $this->assertIsInt($start);
        $this->assertIsInt($end);
        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $lines = explode("\n", $contents);
        return implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
    }

    #[Test]
    public function findSentencesFromWordAppliesParentTextScope(): void
    {
        $source = $this->getMethodSource(SentenceService::class, 'findSentencesFromWord');

        $this->assertStringContainsString(
            'parentTextUserScope',
            $source,
            'findSentencesFromWord must call parentTextUserScope() so'
            . ' the sentences/word_occurrences raw SQL filters by'
            . ' user_id via the parent text. Otherwise an authenticated'
            . ' user can pass another user\'s language_id and read'
            . ' their sentences.'
        );
    }

    #[Test]
    public function parentTextUserScopeAddsSeTxIdSubquery(): void
    {
        $source = $this->getMethodSource(SentenceService::class, 'parentTextUserScope');

        $this->assertStringContainsString(
            'text_id IN (SELECT id FROM texts WHERE user_id = ?)',
            $source,
            'parentTextUserScope must scope sentences via the parent'
            . ' texts row — text_id has no user_id of its own.'
        );
    }

    #[Test]
    public function formatSentenceGuardsOnOwnsSentence(): void
    {
        $source = $this->getMethodSource(SentenceService::class, 'formatSentence');

        $this->assertStringContainsString(
            'ownsSentence',
            $source,
            'formatSentence must short-circuit on ownsSentence() before'
            . ' running the word_occurrences/languages content query —'
            . ' that query has no user_id filter and would otherwise return'
            . ' the foreign user\'s sentence text verbatim.'
        );
    }

    #[Test]
    public function ownsSentenceFiltersByTxUsID(): void
    {
        $source = $this->getMethodSource(SentenceService::class, 'ownsSentence');

        $this->assertStringContainsString(
            'user_id = ?',
            $source,
            'ownsSentence must compare the sentence\'s parent user_id'
            . ' against the current user.'
        );
    }
}
