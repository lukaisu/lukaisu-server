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
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Activity\Http;

use Lukaisu\Shared\Http\ApiRoutableInterface;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Api\V1\Response;
use Lukaisu\Modules\Activity\Application\ActivityFacade;

/**
 * API handler for activity and streak endpoints.
 *
 * @since 3.0.0
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
            default => Response::error('Unknown activity endpoint', 404),
        };
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
