<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\ErrorHandler\ErrorHandlerPlugin;
use Testo\ErrorHandler\Internal\ErrorHandlerInterceptor;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\ErrorHandler\Stub\Warning\RaisesWarningStub;

/**
 * A plugin listed in a suite's `plugins` lands next to the default instance, so the configured one
 * has to win over it.
 */
#[Test]
#[TestingSuite(path: __DIR__ . '/../Stub/Warning', plugins: [new ErrorHandlerPlugin(failOnError: true)])]
#[Covers(ErrorHandlerInterceptor::class)]
final class ConfiguredPluginFeatureTest
{
    public function configuredInstanceOverridesTheDefault(): void
    {
        $result = TestRunner::runTest([RaisesWarningStub::class, 'raisesWarning']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, \ErrorException::class);
        Assert::same($result->failure?->getMessage(), 'Stub warning');
    }
}
