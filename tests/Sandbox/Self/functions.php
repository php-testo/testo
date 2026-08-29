<?php

declare(strict_types=1);

namespace Tests\Sandbox\Self;

use Testo\Assert;
use Testo\Filter\Group;
use Testo\Test;

#[Test]
#[Group('sandbox')]
function simpleFunctionAssertions(): void
{
    Assert::same(1, 1);
    Assert::null(null);
    Assert::notSame('42', 42);
}
