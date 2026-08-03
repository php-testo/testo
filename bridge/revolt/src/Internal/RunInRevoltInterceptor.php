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
 * Sits at {@see InterceptorOptions::ORDER_RIGHT_BEFORE_TEST} — inner to everything but coverage — so the
 * loop gets only what actually needs it: the code that awaits. The rest of the pipeline (data provider,
 * retries, the assertion collector, the messenger scope, the Mockery guard) stays off the loop and never
 * parks mid-flight; each dataset or attempt is dispatched as a loop run of its own. The scoped-state
 * guards still reach the body: {@see \Internal\Fiber\FiberLocal} answers a fiber holding no binding of its
 * own from the surrounding scope whenever that is unambiguous, and with one test in flight it always is —
 * for the body's fiber and for every coroutine the body spawns, however deep they nest.
 *
 * **A case's tests are not run concurrently with each other.** Sharing one loop run between them would put
 * several test scopes in flight at once, and that is exactly where the inference above must refuse to
 * answer: PHP gives a fiber no link to its creator, so a spawned coroutine — `EventLoop::queue()`, an
 * `async()` call, the everyday shape of an async test — has an unambiguous owner only while a single test
 * is in flight. Interleaving whole tests is what `testo/fiber`'s `#[RunInFiber]` schedules do, on plain
 * fibers Testo itself drives.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Revolt
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_RIGHT_BEFORE_TEST, onConflict: ConflictPolicy::Last)]
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
