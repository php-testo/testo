<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Child overrides a method that has CoversNothing in the parent.
 */
final class ChildOverridesMethod extends UncoveredBaseMethod
{
    public function testMarkedInBase(): void {}
}
