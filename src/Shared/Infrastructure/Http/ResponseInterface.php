<?php

/**
 * Response Interface
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Shared\Infrastructure\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Infrastructure\Http;

/**
 * Interface for HTTP response objects.
 *
 * Response objects encapsulate HTTP response data (status, headers, body)
 * and can be sent by the router after controller execution.
 */
interface ResponseInterface
{
    /**
     * Send the response to the client.
     *
     * This method outputs headers and body content.
     *
     * @return void
     */
    public function send(): void;

    /**
     * Get the HTTP status code.
     *
     * @return int HTTP status code (e.g., 200, 302, 404)
     */
    public function getStatusCode(): int;
}
