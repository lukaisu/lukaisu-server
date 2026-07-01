<?php

/**
 * Statistics Controller
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\User\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\User\Http;

use Lukaisu\Modules\User\Application\UseCases\Statistics\GetFrequencyStatistics;
use Lukaisu\Modules\User\Application\UseCases\Statistics\GetIntensityStatistics;
use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;

/**
 * Controller for per-user learning statistics.
 *
 * The statistics page (/profile/statistics) is now a Svelte island shipped in
 * the bundle (`dist-app/statistics.html`); the GET page route 302s there. This
 * controller exposes the server-computed chart data the island fetches on
 * mount, plus the legacy /admin/statistics redirect.
 */
class StatisticsController extends BaseController
{
    /**
     * Intensity statistics use case.
     */
    private GetIntensityStatistics $getIntensityStatistics;

    /**
     * Frequency statistics use case.
     */
    private GetFrequencyStatistics $getFrequencyStatistics;

    /**
     * Constructor.
     *
     * @param GetIntensityStatistics|null $getIntensityStatistics Intensity use case
     * @param GetFrequencyStatistics|null $getFrequencyStatistics Frequency use case
     */
    public function __construct(
        ?GetIntensityStatistics $getIntensityStatistics = null,
        ?GetFrequencyStatistics $getFrequencyStatistics = null
    ) {
        parent::__construct();
        $this->getIntensityStatistics = $getIntensityStatistics ?? new GetIntensityStatistics();
        $this->getFrequencyStatistics = $getFrequencyStatistics ?? new GetFrequencyStatistics();
    }

    /**
     * Statistics bootstrap config (JSON).
     *
     * The statistics UI is now a Svelte island in the bundle
     * (`dist-app/statistics.html`); the GET page route 302s there. The island
     * cannot compute the chart data — per-language term-status counts
     * (intensity) and the created/activity/known totals over time windows
     * (frequency) — so it fetches them here on mount. This mirrors the two JSON
     * blobs the retired `statistics.php` view used to inline. The streak +
     * calendar heatmap the island fetches separately from /api/v1/activity/*.
     *
     * Route: GET /profile/statistics/config
     *
     * @param array<string, string> $params Route parameters (unused)
     *
     * @return JsonResponse
     */
    public function config(array $params = []): JsonResponse
    {
        unset($params);

        $intensityStats = $this->getIntensityStatistics->execute();
        $frequencyStats = $this->getFrequencyStatistics->execute();

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

        return JsonResponse::success([
            'intensity' => $intensity,
            'frequency' => $frequency,
        ]);
    }

    /**
     * Redirect the legacy /admin/statistics URL to /profile/statistics.
     *
     * GET /admin/statistics
     *
     * @param array<string, string> $params Route parameters
     *
     * @return RedirectResponse
     */
    public function redirectFromAdmin(array $params = []): RedirectResponse
    {
        return $this->redirect('/profile/statistics', 301);
    }
}
