<?php

declare(strict_types=1);

namespace Testo\Bridge\Revolt\Internal;

use Revolt\EventLoop;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Drives a single {@see RunInRevolt} test as a coroutine on the Revolt event loop.
 *
 * The test body is launched as a microtask (its own loop fiber) and the current fiber blocks on a
 * {@see \Revolt\EventLoop\Suspension} until it completes, so the test may await real async work while
 * staying, from the outside, an ordinary blocking call.
 *
 * Sits at {@see InterceptorOptions::ORDER_CLOSE_TO_TEST}, i.e. **inside** the fiber-aware scoped-state
 * guards (assertion collector, messenger output scope), so those guards stay on their synchronous
 * main-fiber path and do not hand-drive a fiber the Revolt driver also owns (fiber deadlock).
 * Running several guarded tests together on one shared loop is blocked until the guards move to
 * fiber-local state — see the fiber-local migration specs.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Revolt
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, onConflict: ConflictPolicy::Last)]
final readonly class RunInRevoltInterceptor implements TestRunInterceptor
{
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
