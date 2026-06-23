<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Core\Container\Fixtures;

class TestServiceWithDefaults
{
    public function __construct(
        public string $value = 'default'
    ) {
    }
}
