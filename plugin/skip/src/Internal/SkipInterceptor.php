<?php

declare(strict_types=1);

namespace Testo\Skip\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Requires;
use Testo\Skip;

/**
 * Reports a test as skipped instead of running it when it is marked with {@see Skip} or has an
 * unmet {@see Requires}; any other test passes through.
 *
 * Both attributes spawn one instance per declaration, class and function alike, and
 * {@see ConflictPolicy::First} collapses them — so which instance survives says nothing about which
 * attribute applies, and {@see SkipDecision} reads them back from reflection instead.
 *
 * Ordering: inner to the filter, outer to every interceptor that prepares, wraps or multiplies a
 * test body, since none of them has anything to do for a test that has no body to run.
 *
 * Returns the result instead of throwing {@see SkipTest}: a throw leaves the pipeline and lands as
 * {@see Status::Aborted}.
 *
 * @internal
 * @psalm-internal Testo\Skip
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_FILTER + 1_000,
    onConflict: ConflictPolicy::First,
    testType: TestType::Test,
)]
final readonly class SkipInterceptor implements TestRunInterceptor
{
    /**
     * The skip message opens with the test id, the string `--filter` takes back.
     */
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $message = SkipDecision::of($info->testDefinition, $info->caseInfo->definition->reflection);

        return $message === null ? $next($info) : new TestResult(
            info: $info,
            status: Status::Skipped,
            failure: new SkipTest("{$info->identity->fqn()} {$message}"),
            attributes: ['duration' => 0, 'description' => $info->testDefinition->getDescription()],
            summary: Summary::forTest(Status::Skipped),
        );
    }
}
