<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\User\Http;

use Lukaisu\Modules\User\Http\StatisticsController;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StatisticsController.
 *
 * The chart-data config() moved to ActivityApiHandler::statistics
 * (GET /api/v1/activity/statistics) under the headless cut (Phase R); only the
 * legacy /admin/statistics redirect remains here.
 */
class StatisticsControllerTest extends TestCase
{
    private StatisticsController $controller;

    protected function setUp(): void
    {
        $this->controller = new StatisticsController();
    }

    public function testControllerClassExists(): void
    {
        $this->assertInstanceOf(StatisticsController::class, $this->controller);
    }

    public function testRedirectFromAdminReturns301ToProfileStatistics(): void
    {
        $response = $this->controller->redirectFromAdmin([]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/profile/statistics', $response->getUrl());
        $this->assertSame(301, $response->getStatusCode());
    }
}
