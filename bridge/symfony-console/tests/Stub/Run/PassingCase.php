<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Stub\Run;

use Testo\Assert;
use Testo\Test;

/**
 * A minimal case for the `run` command acceptance tests to execute: one test that passes and asserts
 * something, so a nested run has a suite, a case, a test and a green status to report.
 */
#[Test]
final class PassingCase
{
    public function itPasses(): void
    {
        Assert::true(true);
    }
}
