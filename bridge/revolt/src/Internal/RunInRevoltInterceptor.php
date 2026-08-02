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
 * Runs a {@see RunInRevolt} test's pipeline as a coroutine on the Revolt event loop.
 *
 * One test at a time: the test's whole pipeline is launched as a microtask and the calling fiber blocks on
 * a {@see \Revolt\EventLoop\Suspension} until it finishes, so from the outside the test stays an ordinary
 * blocking call while, inside, it may await real async work.
 *
 * Sits **outer** to the fiber-aware scoped-state guards (order just outside {@see
 * InterceptorOptions::ORDER_DATA_PROVIDER}): the loop dispatch wraps the guards, so the assertion
 * collector, the messenger scope and the test body all run in the same loop fiber, and a data-driven or
 * retried test runs all its datasets/attempts inside it (the data provider stays inner to the dispatch).
 *
 * **A case's tests are not run concurrently with each other.** Sharing one loop run between them would put
 * several test scopes in flight at once, and {@see \Internal\Fiber\FiberLocal} can then no longer say which
 * test a coroutine belongs to: PHP gives a fiber no link to its creator, so a fiber the test spawns —
 * `EventLoop::queue()`, an `async()` call, the everyday shape of an async test — has an unambiguous owner
 * only while a single test is in flight. With one test on the loop at a time that inference always holds,
 * and its assertions and output stay attributed however deep it nests its coroutines. Interleaving whole
 * tests is what `testo/fiber`'s `#[RunInFiber]` schedules do, on plain fibers Testo itself drives.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Revolt
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DATA_PROVIDER - 1, onConflict: ConflictPolicy::Last)]
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
