<?php

declare(strict_types=1);

namespace Tests\Output\Stub\JUnit;

/**
 * Stub class used to back `\ReflectionMethod` for JUnit writer tests.
 */
final class SampleTestClass
{
    public function passingTest(): void {}

    public function failingTest(): void {}

    /**
     * Sample description line.
     */
    public function describedTest(): void {}
}
