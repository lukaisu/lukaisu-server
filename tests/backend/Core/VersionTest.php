<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Core;

use Lukaisu\Shared\Infrastructure\ApplicationInfo;
use Lukaisu\Shared\Infrastructure\Globals;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ApplicationInfo class
 */
final class VersionTest extends TestCase
{
    /**
     * Test the display of version as a string
     */
    public function testGetVersion(): void
    {
        $version = ApplicationInfo::getVersion();
        $this->assertIsString($version);
    }

    /**
     * Test the correct format of version as v{3-digit MAJOR}{3-digit MINOR}{3-digit PATCH}
     */
    public function testGetVersionNumber(): void
    {
        $version = ApplicationInfo::getVersionNumber();
        $this->assertTrue(str_starts_with($version, 'v'));
        $this->assertSame(10, strlen($version));
    }
}
