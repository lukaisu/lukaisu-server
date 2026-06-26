<?php

/**
 * Unit tests for GetSetting use case.
 *
 * PHP version 8.1
 *
 * @category Testing
 * @package  Lukaisu\Tests\Modules\Admin\Application\UseCases\Settings
 * @license  Unlicense <http://unlicense.org/>
 */

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Admin\Application\UseCases\Settings;

use Lukaisu\Modules\Admin\Application\UseCases\Settings\GetSetting;
use Lukaisu\Modules\Admin\Domain\SettingsRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the GetSetting use case.
 */
class GetSettingTest extends TestCase
{
    private SettingsRepositoryInterface&MockObject $repository;
    private GetSetting $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(SettingsRepositoryInterface::class);
        $this->useCase = new GetSetting($this->repository);
    }

    #[Test]
    public function executeReturnsSettingValue(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('get')
            ->with('set-theme-dir', '')
            ->willReturn('Dark');

        $result = $this->useCase->execute('set-theme-dir');
        $this->assertSame('Dark', $result);
    }

    #[Test]
    public function executePassesDefaultToRepository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('get')
            ->with('set-tooltip-mode', 'hover')
            ->willReturn('hover');

        $result = $this->useCase->execute('set-tooltip-mode', 'hover');
        $this->assertSame('hover', $result);
    }

    #[Test]
    public function executeReturnsDefaultWhenNotFound(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('get')
            ->with('nonexistent-key', 'fallback')
            ->willReturn('fallback');

        $result = $this->useCase->execute('nonexistent-key', 'fallback');
        $this->assertSame('fallback', $result);
    }

    #[Test]
    public function executeReturnsEmptyStringByDefault(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('get')
            ->with('missing-key', '')
            ->willReturn('');

        $result = $this->useCase->execute('missing-key');
        $this->assertSame('', $result);
    }
}
