<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Text\Http;

use Lukaisu\Modules\Text\Http\TextApiHandler;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for POST /texts create input routing/validation.
 *
 * The langId guard returns before any facade/container resolution, so it is
 * exercised here without a database. The happy path (plain create via
 * TextFacade::createText, or auto-split into a book via BookFacade) runs under
 * the container-resolved facades and is covered by their own DB-backed tests.
 */
class TextApiHandlerCreateTest extends TestCase
{
    private TextApiHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TextApiHandler(null);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function post(array $params): JsonResponse
    {
        return $this->handler->routePost(['texts'], $params);
    }

    public function testBarePostReachesCreateNotIdError(): void
    {
        // Bare POST /texts now routes into create instead of 404ing on a
        // missing text id; the langId guard is the first thing it hits.
        $res = $this->post([]);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(['error' => 'langId is required'], $res->getData());
    }

    public function testRejectsZeroLangId(): void
    {
        $res = $this->post(['langId' => 0, 'title' => 'x', 'text' => 'y']);

        $this->assertSame(400, $res->getStatusCode());
        $this->assertSame(['error' => 'langId is required'], $res->getData());
    }

    public function testNonNumericPathStillRejected(): void
    {
        // A non-empty, non-numeric first fragment is still a 404 (it is neither
        // a known sub-resource nor a text id).
        $res = $this->handler->routePost(['texts', 'gibberish'], []);

        $this->assertSame(404, $res->getStatusCode());
        $this->assertSame(['error' => 'Text ID (Integer) Expected'], $res->getData());
    }
}
