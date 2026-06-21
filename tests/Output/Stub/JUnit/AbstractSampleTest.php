<?php

declare(strict_types=1);

namespace Tests\Output\Stub\JUnit;

/**
 * Abstract base whose test method is inherited by concrete subclasses, used to
 * back `\ReflectionMethod` for JUnit writer tests of the inheritance case.
 */
abstract class AbstractSampleTest
{
    public function inheritedTest(): void {}
}
