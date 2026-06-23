<?php declare(strict_types=1);
/**
 * Lukaisu Server Front Controller
 *
 * This file serves as the single entry point for all requests.
 * It bootstraps the application and delegates to the Application class.
 *
 * PHP version 8.1
 *
 * @category User_Interface
 * @package Lukaisu
 * @author  Lukaisu Server Project <lukaisu-project@hotmail.com>
 * @license Unlicense <http://unlicense.org/>
 * @link    https://hugofara.github.io/lukaisu-server/developer/api
 * @since   3.0.0
 *
 * "Lukaisu Server" (Lukaisu Server) is free and unencumbered software
 * released into the PUBLIC DOMAIN.
 */

// Define base path constant
define('LUKAISU_BASE_PATH', __DIR__);

// Load Composer autoloader for PSR-4 class autoloading
require_once LUKAISU_BASE_PATH . '/vendor/autoload.php';

// Create and run the application
$app = new \Lukaisu\Application(LUKAISU_BASE_PATH);
$app->bootstrap();
$app->run();
