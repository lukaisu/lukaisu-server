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
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\User\Http;

use Lukaisu\Modules\User\Application\UseCases\Statistics\GetFrequencyStatistics;
use Lukaisu\Modules\User\Application\UseCases\Statistics\GetIntensityStatistics;
use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;

/**
 * Controller for per-user learning statistics.
 *
 * Displays reading intensity and frequency statistics scoped to the
 * current user at /profile/statistics.
 *
 * @since 3.0.0
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
     * Path to view templates.
     */
    private string $viewPath;

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
        $this->viewPath = __DIR__ . '/../Views/';
    }

    /**
     * Display the statistics page.
     *
     * GET /profile/statistics
     *
     * @param array<string, string> $params Route parameters
     *
     * @return void
     *
     * @psalm-suppress UnusedVariable Variables are used in included view files
     * @psalm-suppress UnresolvableInclude View path is constructed at runtime
     */
    public function show(array $params = []): void
    {
        $intensityStats = $this->getIntensityStatistics->execute();
        $frequencyStats = $this->getFrequencyStatistics->execute();

        $this->render('Statistics', true);
        include $this->viewPath . 'statistics.php';
        $this->endRender();
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
