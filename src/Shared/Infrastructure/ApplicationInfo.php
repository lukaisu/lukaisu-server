<?php

/**
 * \file
 * \brief Application information and version utilities.
 *
 * PHP version 8.1
 *
 * @category Core
 * @package  Lukaisu\Shared\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Infrastructure;

/**
 * Application information and version utilities.
 *
 * Provides version information and utility methods for the Lukaisu Server application.
 */
class ApplicationInfo
{
    /**
     * Version of this current Lukaisu Server application.
     */
    public const VERSION = '0.1.0';

    /**
     * Date of the latest published release of Lukaisu Server.
     */
    public const RELEASE_DATE = '2026-06-23';

    /**
     * Get the application version for display to humans.
     *
     * @return string Version number with formatted date (e.g., "0.1.0 (June 23 2026)")
     */
    public static function getVersion(): string
    {
        $timestamp = \strtotime(self::RELEASE_DATE);
        $formattedDate = $timestamp !== false ? \date("F d Y", $timestamp) : self::RELEASE_DATE;
        return self::VERSION . " ($formattedDate)";
    }

    /**
     * Get a machine-readable version number.
     *
     * @return string Machine-readable version (e.g., "v000.001.000" for version 0.1.0)
     */
    public static function getVersionNumber(): string
    {
        $r = 'v';
        $v = self::getVersion();
        // Escape any pre-release / build suffix (e.g. "-rc1")
        $v = \preg_replace('/-\w+\d*/', '', $v) ?? $v;
        $pos = \strpos($v, ' ', 0);
        if ($pos === false) {
            throw new \InvalidArgumentException(
                "Invalid version format '$v': expected 'X.Y.Z (date)'"
            );
        }
        $vn = \preg_split("/[.]/", \substr($v, 0, $pos));
        if ($vn === false || \count($vn) < 3) {
            throw new \InvalidArgumentException(
                "Invalid version format '$v':"
                . " expected at least 3 version components (X.Y.Z)"
            );
        }
        $r .= \substr('000' . $vn[0], -3);
        $r .= \substr('000' . $vn[1], -3);
        $r .= \substr('000' . $vn[2], -3);
        return $r;
    }

    /**
     * Get the raw version string without formatting.
     *
     * @return string Raw version string (e.g., "0.1.0")
     */
    public static function getRawVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get the release date.
     *
     * @return string Release date in YYYY-MM-DD format
     */
    public static function getReleaseDate(): string
    {
        return self::RELEASE_DATE;
    }
}
