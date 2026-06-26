<?php

/**
 * WordPress Controller
 *
 * Controller for WordPress integration endpoints.
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
use Lukaisu\Shared\Infrastructure\Exception\AuthException;
use Lukaisu\Modules\User\Application\Services\WordPressAuthService;

/**
 * Controller for WordPress integration.
 *
 * Handles:
 * - WordPress start (login flow)
 * - WordPress stop (logout flow)
 */
class WordPressController extends BaseController
{
    /**
     * @var WordPressAuthService WordPress auth service instance
     */
    protected WordPressAuthService $wordPressAuthService;

    /**
     * Create a new WordPressController.
     *
     * @param WordPressAuthService $wordPressAuthService WordPress auth service
     */
    public function __construct(WordPressAuthService $wordPressAuthService)
    {
        parent::__construct();
        $this->wordPressAuthService = $wordPressAuthService;
    }

    /**
     * Get the WordPress auth service instance.
     *
     * @return WordPressAuthService
     */
    public function getWordPressAuthService(): WordPressAuthService
    {
        return $this->wordPressAuthService;
    }

    /**
     * WordPress start - handle login flow (replaces wordpress_start.php)
     *
     * To start Lukaisu Server with WordPress login, use this URL:
     * http://...path-to-wp-blog.../lukaisu-server/wp_lukaisu_start.php
     *
     * Cookies must be enabled. A session cookie will be set.
     * The Lukaisu Server installation must be in sub directory "lukaisu-server" under
     * the WordPress main directory.
     *
     * @param array<string, mixed> $params Route parameters
     *
     * @return void
     */
    public function start(array $params): void
    {
        $redirectUrl = $this->param('rd');

        $result = $this->wordPressAuthService->handleStart($redirectUrl);

        if (!$result['success'] && $result['error'] !== null) {
            throw new AuthException($result['error']);
        }

        $this->redirect($result['redirect']);
    }

    /**
     * WordPress stop - handle logout flow (replaces wordpress_stop.php)
     *
     * To properly log out from both WordPress and Lukaisu Server, use:
     * http://...path-to-wp-blog.../lukaisu-server/wp_lukaisu_stop.php
     *
     * @param array<string, mixed> $params Route parameters
     *
     * @return void
     */
    public function stop(array $params): void
    {
        $result = $this->wordPressAuthService->handleStop();

        $this->redirect($result['redirect']);
    }
}
