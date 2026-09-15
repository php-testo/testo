<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Assert;
use Testo\Test;
use Testo\Skip;

# Proves #[Skip] reaches a function-based case as well: the test is reported as Skipped and its
# message is built from the function FQN.
#[Test]
#[Skip('functional test is skipped')]
function skippedFunction(): void
{
    throw new \LogicException('Must never run: the test is skipped.');
}

# Control neighbor of the same case: an enabled function next to a skipped one still runs through
# the batch runner the interceptor installs on the case, and passes.
#[Test]
function enabledFunction(): void
{
    Assert::true(true);
}
