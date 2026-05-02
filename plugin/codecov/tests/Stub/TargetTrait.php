<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Dummy trait used as a coverage target.
 */
trait TargetTrait
{
    public function greet(string $name): string
    {
        return 'Hello, ' . $name;
    }

    public function farewell(string $name): string
    {
        return 'Goodbye, ' . $name;
    }
}
