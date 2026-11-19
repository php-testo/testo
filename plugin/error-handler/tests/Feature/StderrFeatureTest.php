<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Core\Value\Status;
use Testo\ErrorHandler\Internal\ErrorHandlerInterceptor;
use Testo\ErrorHandler\Internal\HandlerScope;
use Testo\ErrorHandler\WithoutErrorHandler;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\ErrorHandler\Stub\Warning\RaisesWarningStub;

/**
 * An unhandled error reaches the test's output next to whatever else the test wrote, such as the
 * assertion history.
 *
 * The outer run stays out of the plugin: its handler would take the stub's error as handled.
 */
#[Test]
#[TestingSuite(path: __DIR__ . '/../Stub/Warning')]
#[Covers(ErrorHandlerInterceptor::class)]
#[Covers(HandlerScope::class)]
#[WithoutErrorHandler]
final class StderrFeatureTest
{
    public function unhandledErrorReachesTheTestOutput(): void
    {
        $result = TestRunner::runTest([RaisesWarningStub::class, 'raisesWarning']);

        Assert::same($result->status, Status::Passed);
        $stderr = $result->messages->channel(Messenger::CHANNEL_STDERR);
        Assert::count($stderr, 1);
        Assert::string($stderr[0]->content)->startsWith('Warning: Stub warning in ');
    }
}
