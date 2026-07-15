<?php

declare(strict_types=1);

namespace Tests\Bridge\VCR\Feature;

use Testo\Assert;
use Testo\Bridge\VCR\Internal\VcrInterceptor;
use Testo\Bridge\VCR\VcrPlugin;
use Testo\Bridge\VCR;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Bridge\VCR\Stub\VcrScenarios;

/**
 * How the VCR bridge maps onto Testo's test statuses. Each case runs a stub scenario through
 * {@see TestRunner} (with {@see VcrPlugin} loaded and pointed at the fixtures) and asserts the
 * resulting {@see Status}.
 */
#[Test]
#[Covers(VcrPlugin::class, VcrInterceptor::class, VCR::class)]
#[TestingSuite(path: __DIR__ . '/../Stub', plugins: [new VcrPlugin(cassettePath: __DIR__ . '/../fixtures')])]
final class VcrStatusTest
{
    public function replayHitPasses(): void
    {
        $result = TestRunner::runTest([VcrScenarios::class, 'replaysRecordedResponse']);
        Assert::same($result->status, Status::Passed);
    }

    public function unrecordedRequestInNoneModeFails(): void
    {
        $result = TestRunner::runTest([VcrScenarios::class, 'unrecordedRequestInNoneModeFails']);
        Assert::same($result->status, Status::Error);
    }

    public function untaggedTestPassesThrough(): void
    {
        $result = TestRunner::runTest([VcrScenarios::class, 'untaggedTestPassesThrough']);
        Assert::same($result->status, Status::Passed);
    }
}
