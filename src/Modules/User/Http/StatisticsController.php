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

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;

/**
 * Controller for the legacy /admin/statistics redirect.
 *
 * The statistics page (/profile/statistics) is a Svelte island in the bundle
 * (the GET page route 302s there), and its chart data moved to
 * GET /api/v1/activity/statistics (ActivityApiHandler::statistics) under the
 * headless cut (Phase R). All that remains here is the legacy admin redirect.
 */
class StatisticsController extends BaseController
{
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
