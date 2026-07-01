<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\User\Http;

use Lukaisu\Modules\User\Application\UseCases\Statistics\GetFrequencyStatistics;
use Lukaisu\Modules\User\Application\UseCases\Statistics\GetIntensityStatistics;
use Lukaisu\Modules\User\Http\StatisticsController;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StatisticsController.
 */
class StatisticsControllerTest extends TestCase
{
    /** @var GetIntensityStatistics&MockObject */
    private GetIntensityStatistics $intensity;

    /** @var GetFrequencyStatistics&MockObject */
    private GetFrequencyStatistics $frequency;

    private StatisticsController $controller;

    protected function setUp(): void
    {
        $this->intensity = $this->createMock(GetIntensityStatistics::class);
        $this->frequency = $this->createMock(GetFrequencyStatistics::class);
        $this->controller = new StatisticsController($this->intensity, $this->frequency);
    }

    public function testControllerClassExists(): void
    {
        $this->assertInstanceOf(StatisticsController::class, $this->controller);
    }

    public function testConfigCallsBothUseCasesAndShapesChartData(): void
    {
        $this->intensity->expects($this->once())
            ->method('execute')
            ->willReturn([
                'languages' => [
                    [
                        'name' => 'English',
                        's1' => 10, 's2' => 20, 's3' => 30,
                        's4' => 15, 's5' => 25, 's99' => 100,
                    ],
                ],
                'totals' => [],
            ]);

        $this->frequency->expects($this->once())
            ->method('execute')
            ->willReturn([
                'languages' => [],
                'totals' => [
                    'ct' => 5, 'at' => 10, 'kt' => 2,
                    'cy' => 3, 'ay' => 8, 'ky' => 1,
                    'cw' => 20, 'aw' => 50, 'kw' => 10,
                    'cm' => 100, 'am' => 200, 'km' => 50,
                    'ca' => 500, 'aa' => 1000, 'ka' => 300,
                ],
            ]);

        $response = $this->controller->config([]);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = $response->getData();
        $this->assertIsArray($data);

        $this->assertSame([
            [
                'name' => 'English',
                's1' => 10, 's2' => 20, 's3' => 30,
                's4' => 15, 's5' => 25, 's99' => 100,
            ],
        ], $data['intensity']);

        $this->assertSame(5, $data['frequency']['ct']);
        $this->assertSame(300, $data['frequency']['ka']);
    }

    public function testRedirectFromAdminReturns301ToProfileStatistics(): void
    {
        $response = $this->controller->redirectFromAdmin([]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/profile/statistics', $response->getUrl());
        $this->assertSame(301, $response->getStatusCode());
    }
}
