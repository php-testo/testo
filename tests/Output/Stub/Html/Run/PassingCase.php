<?php

declare(strict_types=1);

namespace Tests\Output\Stub\Html\Run;

use Testo\Assert;
use Testo\Test;

/**
 * A minimal case for the feature tests to run: one test that passes, prints, and asserts something, so a
 * generated report has a suite, a case, a test, an assertion count and channel output to describe.
 */
#[Test]
final class PassingCase
{
    public function itPrintsAndPasses(): void
    {
        echo "from the test\n";

        Assert::true(true);
    }
}
