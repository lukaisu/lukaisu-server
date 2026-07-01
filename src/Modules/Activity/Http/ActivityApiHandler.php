<?php

/**
 * Activity API Handler
 *
 * PHP version 8.2
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Activity\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Activity\Http;

use Lukaisu\Shared\Http\ApiRoutableInterface;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Api\V1\Response;
use Lukaisu\Modules\Activity\Application\ActivityFacade;
use Lukaisu\Modules\User\Application\UseCases\Statistics\GetIntensityStatistics;
use Lukaisu\Modules\User\Application\UseCases\Statistics\GetFrequencyStatistics;

/**
 * API handler for activity and streak endpoints.
 */
class ActivityApiHandler implements ApiRoutableInterface
{
    private ActivityFacade $facade;

    /**
     * Constructor.
     *
     * @param ActivityFacade $facade Activity facade
     */
    public function __construct(ActivityFacade $facade)
    {
        $this->facade = $facade;
    }

    /**
     * {@inheritdoc}
     */
    public function routeGet(array $fragments, array $params): JsonResponse
    {
        $subRoute = $fragments[1] ?? '';

        return match ($subRoute) {
            'streak' => Response::success($this->facade->getStreakStatistics()->toArray()),
            'calendar' => Response::success($this->facade->getCalendarHeatmapData()),
            'today' => Response::success($this->facade->getTodaySummary()),
            'dashboard' => Response::success([
                'streak' => $this->facade->getStreakStatistics()->toArray(),
                'calendar' => $this->facade->getCalendarHeatmapData(),
                'today' => $this->facade->getTodaySummary(),
            ]),
            'statistics' => $this->statistics(),
            default => Response::error('Unknown activity endpoint', 404),
        };
    }

    /**
     * Per-user learning-statistics chart data.
     *
     * Moved from the retired StatisticsController@config (GET
     * /profile/statistics/config) under the headless cut (Phase R): per-language
     * term-status counts (intensity) + created/activity/known totals over rolling
     * windows (frequency). The StatisticsPage island fetches this on mount
     * alongside activity/streak + activity/calendar.
     *
     * @return JsonResponse
     */
    private function statistics(): JsonResponse
    {
        $intensityStats = (new GetIntensityStatistics())->execute();
        $frequencyStats = (new GetFrequencyStatistics())->execute();

        $intensity = [];
        /** @var mixed $lang */
        foreach (($intensityStats['languages'] ?? []) as $lang) {
            if (!is_array($lang)) {
                continue;
            }
            $intensity[] = [
                'name' => (string) ($lang['name'] ?? ''),
                's1' => (int) ($lang['s1'] ?? 0),
                's2' => (int) ($lang['s2'] ?? 0),
                's3' => (int) ($lang['s3'] ?? 0),
                's4' => (int) ($lang['s4'] ?? 0),
                's5' => (int) ($lang['s5'] ?? 0),
                's99' => (int) ($lang['s99'] ?? 0),
            ];
        }

        $totals = is_array($frequencyStats['totals'] ?? null) ? $frequencyStats['totals'] : [];
        $frequency = [];
        foreach (
            [
                'ct', 'at', 'kt', 'cy', 'ay', 'ky', 'cw', 'aw', 'kw',
                'cm', 'am', 'km', 'ca', 'aa', 'ka',
            ] as $key
        ) {
            $frequency[$key] = (int) ($totals[$key] ?? 0);
        }

        return Response::success([
            'intensity' => $intensity,
            'frequency' => $frequency,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function routePost(array $fragments, array $params): JsonResponse
    {
        return Response::error('Method Not Allowed', 405);
    }

    /**
     * {@inheritdoc}
     */
    public function routePut(array $fragments, array $params): JsonResponse
    {
        return Response::error('Method Not Allowed', 405);
    }

    /**
     * {@inheritdoc}
     */
    public function routeDelete(array $fragments, array $params): JsonResponse
    {
        return Response::error('Method Not Allowed', 405);
    }
}
