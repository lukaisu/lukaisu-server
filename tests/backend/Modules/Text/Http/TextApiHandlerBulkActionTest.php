<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Text\Http;

use Lukaisu\Modules\Text\Http\TextApiHandler;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PUT /texts/bulk-action input validation.
 *
 * The validation branches (bad action, malformed/empty ids) return before the
 * TextFacade is resolved, so they are exercised here without a database. The
 * happy path (archive/delete) runs under per-user QueryBuilder scoping and is
 * covered by the facade's own DB-backed tests.
 */
class TextApiHandlerBulkActionTest extends TestCase
{
    private TextApiHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TextApiHandler(null);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function bulk(array $params): JsonResponse
    {
        return $this->handler->routePut(['texts', 'bulk-action'], $params);
    }

    public function testRejectsUnknownAction(): void
    {
        $res = $this->bulk(['action' => 'reparse', 'ids' => [1, 2]]);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(
            ['error' => 'Expected one of: archive, delete, rebuild, '
                . 'set-sentences, set-active-sentences, add-tag, remove-tag'],
            $res->getData()
        );
    }

    public function testRejectsMissingAction(): void
    {
        $res = $this->bulk(['ids' => [1]]);
        $this->assertSame(400, $res->getStatusCode());
    }

    public function testRejectsNonArrayIds(): void
    {
        $res = $this->bulk(['action' => 'delete', 'ids' => '1,2,3']);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(['error' => 'ids must be an array'], $res->getData());
    }

    public function testRejectsEmptyIds(): void
    {
        $res = $this->bulk(['action' => 'archive', 'ids' => []]);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(['error' => 'No text IDs provided'], $res->getData());
    }

    public function testRejectsIdsThatFilterToEmpty(): void
    {
        // Zero and negative IDs are filtered out, leaving nothing to act on.
        $res = $this->bulk(['action' => 'delete', 'ids' => [0, -5, 'abc']]);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(['error' => 'No text IDs provided'], $res->getData());
    }

    public function testMissingIdsKeyTreatedAsEmpty(): void
    {
        $res = $this->bulk(['action' => 'archive']);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(['error' => 'No text IDs provided'], $res->getData());
    }

    public function testTagActionRequiresTag(): void
    {
        // add-tag / remove-tag need a non-empty tag; validated before any facade.
        $res = $this->bulk(['action' => 'add-tag', 'ids' => [1, 2]]);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(['error' => 'tag is required for tag actions'], $res->getData());
    }

    public function testArchivedScopeRejectsActiveOnlyAction(): void
    {
        // rebuild / set-sentences are active-only; the archived scope rejects them.
        $res = $this->bulk(['action' => 'rebuild', 'ids' => [1], 'archived' => true]);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(
            ['error' => 'Expected one of: delete, unarchive, add-tag, remove-tag'],
            $res->getData()
        );
    }
}
