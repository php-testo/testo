<?php

declare(strict_types=1);

namespace Testo\Bridge\Revolt\Internal;

use Revolt\EventLoop;
use Testo\Bridge\Revolt\Strategy;
use Testo\Core\Context\TestResult;

/**
 * Drives a case's test handlers **concurrently** on the process-global **Revolt** event loop.
 *
 * An invokable runner — set on {@see \Testo\Core\Context\CaseInfo::$batchRunner} by
 * {@see RunInRevoltInterceptor::runTestCase()} under {@see Strategy::PerCase}. Every handler is launched
 * as its own microtask (loop fiber) **at once**; they interleave at their await points and the calling
 * fiber blocks on a {@see \Revolt\EventLoop\Suspension} until all of them finish.
 *
 * This runs the whole per-test pipeline — including Testo's scoped-state guards — inside a loop fiber.
 * The guards hold their state per fiber (see {@see \Testo\Common\FiberLocal}), so concurrent tests keep
 * separate assertion/messenger state across the interleave. See {@see Strategy::PerCase}.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Revolt
 */
final class RevoltTestBatchRunner
{
    /**
     * Depth of loop-driven tests currently in flight. Lets {@see RunInRevoltInterceptor::runTest()}
     * skip its own loop dispatch when a case-level runner already put the test on the loop.
     */
    private static int $depth = 0;

    /**
     * Whether a test is currently being driven on the loop by a runner.
     */
    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Runs a single handler as a coroutine on the loop and blocks the current fiber until it returns.
     *
     * @param callable(): TestResult $handler
     */
    public static function runOnLoop(callable $handler): TestResult
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $handler): void {
            ++self::$depth;
            try {
                $suspension->resume($handler());
            } catch (\Throwable $e) {
                $suspension->throw($e);
            } finally {
                --self::$depth;
            }
        });

        /** @var TestResult */
        return $suspension->suspend();
    }

    /**
     * Launches every handler as its own coroutine on the loop at once and blocks the current fiber
     * until all of them finish, preserving input order in the result.
     *
     * @param list<callable(): TestResult> $handlers
     * @return list<TestResult>
     */
    public function __invoke(array $handlers): array
    {
        if ($handlers === []) {
            return [];
        }

        $suspension = EventLoop::getSuspension();
        $results = [];
        $errors = [];
        $pending = \count($handlers);

        foreach ($handlers as $i => $handler) {
            EventLoop::queue(static function () use ($i, $handler, $suspension, &$results, &$errors, &$pending): void {
                ++self::$depth;
                try {
                    $results[$i] = $handler();
                } catch (\Throwable $e) {
                    $errors[$i] = $e;
                } finally {
                    --self::$depth;
                    --$pending === 0 and $suspension->resume();
                }
            });
        }

        $suspension->suspend();

        // Safety net: the loop must not unwind before every test finished. If a handler ever strands its
        // loop fiber (a bare suspend with no resumer), this suspension would return with tests missing —
        // fail loudly rather than silently dropping tests (a green run that ran nothing is the worst
        // outcome). With the fiber-local guards in place this never fires in practice.
        $done = \count($results) + \count($errors);
        $done === \count($handlers) or throw new \RuntimeException(\sprintf(
            'Revolt PerCase lost %d of %d test(s): the event loop unwound before they finished.',
            \count($handlers) - $done,
            \count($handlers),
        ));

        $errors === [] or throw \reset($errors);

        \ksort($results);

        return \array_values($results);
    }
}
