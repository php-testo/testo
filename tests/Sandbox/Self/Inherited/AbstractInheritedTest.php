<?php

declare(strict_types=1);

namespace Tests\Sandbox\Self\Inherited;

use Testo\Assert;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Abstract base whose #[Test] methods are inherited by concrete subclasses. Used to sanity-check
 * that discovery, naming and `--filter` (by class and by method) attribute inherited tests to the
 * concrete subclass rather than this base.
 */
#[Group('sandbox')]
abstract class AbstractInheritedTest
{
    #[Test]
    public function inheritedFromBase(): void
    {
        Assert::true(true);
    }

    #[Test]
    public function alsoInheritedFromBase(): void
    {
        Assert::true(true);
    }

    public function withoutAttribute(): void
    {
        Assert::true(true);
    }
}
