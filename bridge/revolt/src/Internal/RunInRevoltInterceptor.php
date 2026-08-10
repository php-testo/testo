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
 * Runs a {@see RunInRevolt} test body as a coroutine on the Revolt event loop.
 *
 * One test at a time: the body is launched as a microtask and the calling fiber blocks on a
 * {@see \Revolt\EventLoop\Suspension} until it finishes, so from the outside the test stays an ordinary
 * blocking call while, inside, it may await real async work.
 *
 * Sits at {@see InterceptorOptions::ORDER_ASYNC_COROUTINE} so the loop gets only what
 * actually needs it: the code that awaits. The rest of the pipeline (data provider, retries, the assertion
 * collector, the messenger scope, the Mockery guard, coverage collection) stays off the loop and never
 * parks mid-flight; each dataset or attempt is dispatched as a loop run of its own. That placement is
 * what makes the scoped-state guards and the loop compatible: the guards keep their state in a
 * process-global slot they swap at fiber switches they drive themselves, and here they open their scopes
 * outside the loop and never suspend — so for the whole dispatch the slot simply holds this test's state,
 * and the body reads it from its loop fiber, as does every coroutine the body spawns, however deep.
 *
 * Nothing may be scheduled inner to this: the loop fiber belongs to the Revolt driver, which resumes it
 * directly, past anything wrapping it. An interceptor that suspended out of that fiber — as the coverage
 * trampoline does to keep interleaved tests apart — would never be resumed, and the run would die on
 * "Event loop terminated without resuming the current suspension".
 *
 * **A case's tests are not run concurrently with each other.** Sharing one loop run between them would
 * need the guards to swap that slot at the loop's own switches — and those belong to the Revolt driver,
 * which resumes parked fibers directly, past anything wrapping them. Interleaving whole tests is what
 * `testo/fiber`'s `#[RunInFiber]` schedules do, on plain fibers Testo itself drives — there the guards own
 * every switch.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Revolt
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASYNC_COROUTINE, onConflict: ConflictPolicy::Last)]
final readonly class RunInRevoltInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        return self::runOnLoop(static fn(): TestResult => $next($info));
    }

    /**
     * Runs $handler as a coroutine on the loop and blocks the current fiber until it returns.
     *
     * @param callable(): TestResult $handler
     */
    private static function runOnLoop(callable $handler): TestResult
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $handler): void {
            try {
                $suspension->resume($handler());
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
        });

        /** @var TestResult */
        return $suspension->suspend();
    }
}
