<?php

declare(strict_types=1);

namespace Testo\Fiber\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Exception\CancelledException;
use Testo\Fiber\Exception\CompositeException;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Opens the coroutine scope of a {@see RunInFiber} test: the test body becomes task #0 of a per-test
 * {@see Scheduler}, and {@see Coroutine::spawn()} adds more tasks to the same schedule. The scope
 * relays control upward between rounds, so its coroutines keep interleaving with whatever schedule
 * drives the test — the case batch of a class-level `#[RunInFiber]`, or the single method-level fiber.
 *
 * Sits at {@see InterceptorOptions::ORDER_ASYNC_COROUTINE} — the innermost position, *inside* both the
 * fiber-aware scoped-state guards (assertion collector, messenger scope) and the coverage window,
 * unlike {@see RunInFiberInterceptor} which wraps them. That placement is load-bearing in both
 * directions: coroutines are only ever resumed from this scope's drive frame, so each one runs with
 * its test's state swapped in and inside its coverage window — assertions, messages and executed
 * lines are all attributed to the test that spawned it. Were the scope outer to
 * {@see InterceptorOptions::ORDER_COVERAGE}, the collector's trampoline would close the window every
 * time the body relayed a suspension to us, and everything the coroutines then ran would be measured
 * for nobody.
 *
 * Unlike an event loop at the same order (`testo/bridge-revolt`), this scope drives plain fibers Testo
 * owns and relays suspensions outward, so the trampoline above keeps working: what it must not have
 * above it is a driver that resumes fibers past its wrapper.
 *
 * Coroutine failures nobody awaited fail the test: they are bundled into a {@see CompositeException}
 * (always, even a single one) and attached to the result as its failure with {@see Status::Error}.
 * The body's own throw is captured as a result by the pipeline below and stays unwrapped. A body that
 * settles with a failed result cancels its pending coroutines ({@see Scheduler::drive()}'s failure
 * predicate) instead of driving them further; one that never got to start is simply dropped.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASYNC_COROUTINE, onConflict: ConflictPolicy::Last)]
final readonly class CoroutineScopeInterceptor implements TestRunInterceptor
{
    public function __construct(
        private RunInFiber $options,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        // The scope is always RoundRobin: the attribute's Schedule governs how *tests* interleave,
        // while inside a scope the body and its coroutines must all get their turn (Solo would never
        // hand the floor to a spawned coroutine until the body parks).
        $scheduler = new Scheduler(Schedule::RoundRobin);
        $body = $scheduler->spawn(static fn(): TestResult => $next($info));

        // The pipeline below captures test throwables into the result, so a failed body settles with
        // no error on the task — the predicate is how the scheduler learns to cancel pending coroutines.
        $scheduler->drive($body, static fn(Task $task): bool =>
            $task->result instanceof TestResult && $task->result->status->isFailure());

        // The pipeline below captures test throwables as results, so a body error is unexpected
        // infrastructure breakage — let it abort the pipeline.
        $body->error === null or throw $body->error;

        /** @var TestResult $result */
        $result = $body->result;

        // Surface coroutine failures nobody awaited. An error rethrown by await() was observed —
        // it already went through the body (and is part of its result); cancellations are ours.
        $errors = [];
        foreach ($scheduler->tasks() as $id => $task) {
            if ($task === $body || $task->error === null || $task->errorObserved
                || $task->error instanceof CancelledException
            ) {
                continue;
            }

            $errors[$id] = $task->error;
        }

        if ($errors !== []) {
            // A failed body keeps its own failure as the root: chain it in front of the coroutine
            // errors so nothing is dropped, and keep the harsher of the two statuses.
            $result->failure === null or $errors = [$body->id => $result->failure] + $errors;

            $failed = $result->status === Status::Failed || $result->status === Status::Error;
            $result = $result
                ->with(status: $failed ? $result->status : Status::Error)
                ->withFailure(new CompositeException($errors));
        }

        return $result;
    }
}
