<?php

/**
 * Not Found Exception
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Shared\Infrastructure\Container
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Infrastructure\Container;

/**
 * Exception thrown when a requested service is not found in the container.
 *
 * @since 3.0.0
 */
class NotFoundException extends ContainerException
{
}
