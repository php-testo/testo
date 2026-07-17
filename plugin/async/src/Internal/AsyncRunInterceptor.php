<?php

declare(strict_types=1);

namespace Testo\Async\Internal;

use Revolt\EventLoop;
use Testo\Async;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Drives a single {@see Async} test as a coroutine on the Revolt event loop.
 *
 * The test body is launched as a microtask (its own loop fiber) and the current fiber blocks on a
 * {@see \Revolt\EventLoop\Suspension} until it completes, so the test may await real async work while
 * staying, from the outside, an ordinary blocking call. The very same code works standalone (the main
 * fiber's `suspend()` runs the loop) and under a {@see \Testo\Concurrent} case run (a nested fiber's
 * `suspend()` yields to the already-running loop) — distinct fibers get distinct suspensions.
 *
 * Sits at {@see InterceptorOptions::ORDER_DEFAULT}, i.e. outside the assertion collector, so scoped
 * state guards run inside the loop fiber and stay correct across suspensions.
 *
 * @internal
 * @psalm-internal Testo\Async
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DEFAULT, onConflict: ConflictPolicy::Last)]
final readonly class AsyncRunInterceptor implements TestRunInterceptor
{
    public function __construct(
        private Async $options,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $next, $info): void {
            try {
                $suspension->resume($next($info));
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
        });

        /** @var TestResult */
        return $suspension->suspend();
    }
}
