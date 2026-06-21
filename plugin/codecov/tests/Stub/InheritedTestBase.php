<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Abstract base whose test method is inherited (not overridden) by concrete
 * subclasses. Carries no coverage attribute, so coverage is collected.
 */
abstract class InheritedTestBase
{
    public function testInherited(): void {}
}
