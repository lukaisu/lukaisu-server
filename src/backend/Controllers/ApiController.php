<?php

/**
 * \file
 * \brief API Controller - REST API endpoints
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license Unlicense <http://unlicense.org/>
 * @link    https://hugofara.github.io/lukaisu-server/developer/api
 * @since   3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Controllers;

use Lukaisu\Api\V1\ApiV1;
use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Routing\Middleware\RateLimitMiddleware;

/**
 * Controller for REST API endpoints.
 *
 * Handles:
 * - Main REST API (v1)
 *
 * Note: Translation APIs (/api/translate, /api/google, /api/glosbe) are now
 * handled directly by Lukaisu\Modules\Dictionary\Http\TranslationController.
 *
 * @category Lukaisu
 * @package  Lukaisu
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class ApiController extends BaseController
{
    /**
     * Initialize the controller.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Main API v1 endpoint.
     *
     * Uses the new ApiV1 handler class for clean separation of concerns.
     * Applies rate limiting before processing the request.
     *
     * @param array $params Route parameters
     *
     * @return void
     */
    public function v1(array $params): void
    {
        // Apply rate limiting before processing API request
        $rateLimiter = new RateLimitMiddleware();
        if (!$rateLimiter->handle()) {
            // Rate limit exceeded - response already sent
            return;
        }

        ApiV1::handleRequest();
    }
}
