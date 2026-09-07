<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Output\Terminal\TerminalPlugin;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Core\Testing\Stub\ReporterStub;

/**
 * A {@see TestRunner} run activates a terminal reporter (as a future `--json` on `runTest` would), and
 * its render must land on the run's own {@see \Testo\Output\ConsoleStreams} — a memory pair by default —
 * never the real process stdout. {@see TestingStdoutLeakTest} drives this under `--json` in a subprocess
 * to prove the machine report stays clean; this in-process check only confirms the run still passes.
 */
#[Test]
#[Covers(TestRunner::class)]
#[TestingSuite(
    path: __DIR__ . '/../Stub/ReporterStub.php',
    plugins: [TerminalPlugin::class],
)]
final class ReporterIsolationFeatureTest
{
    public function reporterRunPassesWithoutTouchingRealStdout(): void
    {
        $result = TestRunner::runTest([ReporterStub::class, 'passes']);

        Assert::same($result->status, Status::Passed);
    }
}
