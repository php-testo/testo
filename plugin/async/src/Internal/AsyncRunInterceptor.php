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
 * staying, from the outside, an ordinary blocking call.
 *
 * Sits at {@see InterceptorOptions::ORDER_CLOSE_TO_TEST}, i.e. **inside** the fiber-aware scoped-state
 * guards (assertion collector, messenger output scope). Those guards must stay on their synchronous
 * path (they run on the main fiber, `\Fiber::getCurrent() === null`) — if they were inside this loop
 * drive they would wrap the test in a nested plain `\Fiber` and re-suspend to their own parent,
 * which is incompatible with Revolt's driver resuming that same fiber (fiber deadlock). Keeping this
 * interceptor closest to the test lets the Revolt-managed test fiber suspend straight to the driver.
 *
 * @internal
 * @psalm-internal Testo\Async
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, onConflict: ConflictPolicy::Last)]
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
