<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Stub;

use Testo\Assert;
use Testo\Test;

/**
 * Passing stub driven through the pipeline by {@see \Testo\Testing\Helper\TestRunner} with a terminal
 * reporter attached, so the reporter has a run to render — used to prove that render never reaches the
 * real process stdout.
 */
#[Test]
final class ReporterStub
{
    public function passes(): void
    {
        echo "reporter-stub-marker\n";
        Assert::true(true);
    }
}
