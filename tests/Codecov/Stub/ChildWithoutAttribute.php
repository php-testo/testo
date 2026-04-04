<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Child class without CoversNothing — but parent has it.
 */
final class ChildWithoutAttribute extends UncoveredBaseClass
{
    public function testOwn(): void {}
}
