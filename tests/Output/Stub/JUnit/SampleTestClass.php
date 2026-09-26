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

    public function throwingTest(): never
    {
        $this->throwFromHelper();
    }

    public function wrappingTest(): never
    {
        try {
            $this->throwFromHelper();
        } catch (\LogicException $e) {
            throw new \RuntimeException('wrapped', 0, $e);
        }
    }

    private function throwFromHelper(): never
    {
        throw new \LogicException('thrown from a helper');
    }
}
