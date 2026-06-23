<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Core\Container\Fixtures;

class TestServiceWithDeps
{
    public function __construct(
        public TestDependency $dependency
    ) {
    }
}
