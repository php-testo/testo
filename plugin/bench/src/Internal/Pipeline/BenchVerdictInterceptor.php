<?php

declare(strict_types=1);

namespace Testo\Bench\Internal\Pipeline;

use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Bench;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Internal\BenchHandler;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Records the benchmark's verdict as an assertion on the current test: `current` (always case 0)
 * should be the fastest, with {@see Bench::$tolerance} headroom over the fastest callable for noise.
 * A pass records a successful assertion — which also keeps the benchmark from being reported as Risky
 * for asserting nothing; a miss fails the test.
 *
 * The verdict is applied to the finished {@see TestResult} rather than thrown from the benchmark
 * body, so a failed benchmark keeps its {@see BenchResult} for the reports.
 *
 * No-op without the Assert plugin (the {@see \class_exists()} guard), so `testo/bench` stays
 * independent of it.
 *
 * @internal
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, testType: TestType::BenchInline)]
final readonly class BenchVerdictInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $result = $next($info);
        $bench = $result->result;
        $attr = $info->getAttribute(Bench::class);

        if (!$bench instanceof BenchResult || !$attr instanceof Bench || !\class_exists(StaticState::class)) {
            return $result;
        }

        $record = BenchHandler::benchmarkVerdict(
            $bench->results,
            \array_map(static fn(CaseSet $case): string => $case->name, $bench->cases),
            $attr->tolerance,
        );

        $state = StaticState::current();
        $state === null or $state->history[] = $record;

        return $record instanceof AssertionException
            ? $result->with(status: Status::Failed)->withFailure($record)
            : $result;
    }
}
