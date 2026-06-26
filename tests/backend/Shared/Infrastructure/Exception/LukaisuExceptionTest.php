<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Shared\Infrastructure\Exception;

use Lukaisu\Shared\Infrastructure\Exception\LukaisuException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the base LukaisuException class.
 */
#[CoversClass(LukaisuException::class)]
class LukaisuExceptionTest extends TestCase
{
    public function testConstructorWithMessage(): void
    {
        $exception = new LukaisuException('Test error message');

        $this->assertSame('Test error message', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new LukaisuException('Test error', 42, $previous);

        $this->assertSame('Test error', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithContext(): void
    {
        $context = ['user_id' => 123, 'action' => 'delete'];
        $exception = new LukaisuException('Test error', 0, null, $context);

        $this->assertSame($context, $exception->getContext());
    }

    public function testWithContext(): void
    {
        $exception = new LukaisuException('Test error');
        $result = $exception->withContext('key', 'value');

        $this->assertSame($exception, $result);
        $this->assertSame(['key' => 'value'], $exception->getContext());
    }

    public function testWithContextMultipleValues(): void
    {
        $exception = new LukaisuException('Test error');
        $exception->withContext('key1', 'value1')
                  ->withContext('key2', 'value2');

        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $exception->getContext());
    }

    public function testDefaultHttpStatusCode(): void
    {
        $exception = new LukaisuException('Test error');

        $this->assertSame(500, $exception->getHttpStatusCode());
    }

    public function testSetHttpStatusCode(): void
    {
        $exception = new LukaisuException('Test error');
        $result = $exception->setHttpStatusCode(404);

        $this->assertSame($exception, $result);
        $this->assertSame(404, $exception->getHttpStatusCode());
    }

    public function testShouldLogDefaultsToTrue(): void
    {
        $exception = new LukaisuException('Test error');

        $this->assertTrue($exception->shouldLog());
    }

    public function testGetUserMessage(): void
    {
        $exception = new LukaisuException('Internal error details');

        // User message should be generic for safety
        $this->assertSame(
            'An unexpected error occurred. Please try again later.',
            $exception->getUserMessage()
        );
    }

    public function testToArrayWithoutTrace(): void
    {
        $exception = new LukaisuException('Test error', 42, null, ['key' => 'value']);
        $exception->setHttpStatusCode(400);

        $array = $exception->toArray(false);

        $this->assertSame(LukaisuException::class, $array['type']);
        $this->assertSame('Test error', $array['message']);
        $this->assertSame(42, $array['code']);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertSame(['key' => 'value'], $array['context']);
        $this->assertSame(400, $array['http_status']);
        $this->assertArrayNotHasKey('trace', $array);
    }

    public function testToArrayWithTrace(): void
    {
        $exception = new LukaisuException('Test error');

        $array = $exception->toArray(true);

        $this->assertArrayHasKey('trace', $array);
        $this->assertIsString($array['trace']);
    }

    public function testToArrayWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new LukaisuException('Test error', 0, $previous);

        $array = $exception->toArray(false);

        $this->assertArrayHasKey('previous', $array);
        $this->assertSame(\RuntimeException::class, $array['previous']['type']);
        $this->assertSame('Previous error', $array['previous']['message']);
    }
}
