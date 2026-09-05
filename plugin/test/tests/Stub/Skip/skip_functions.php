<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Assert;
use Testo\Test;
use Testo\Test\Skip;

#[Test]
#[Skip('functional test is parked')]
function parked_function(): void
{
    throw new \LogicException('Must never run: the test is parked.');
}

#[Test]
function enabled_function(): void
{
    // Control neighbor for the function-scoped case.
    Assert::true(true);
}
