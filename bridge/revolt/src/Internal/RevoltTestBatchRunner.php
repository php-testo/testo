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
 * This runs the whole per-test pipeline — including Testo's fiber-aware scoped-state guards — inside a
 * loop fiber. Those guards hand-drive a nested fiber and re-suspend to their own parent, which the
 * Revolt driver cannot cooperate with, so with the current guards **PerCase deadlocks / clashes test
 * state**. That is intentional on this branch: the fix (fiber-local guards) lands on the main branch —
 * here the PerCase self-tests fail on purpose to mark the blocker. See {@see Strategy::PerCase}.
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

        // The loop must not unwind before every test finished. With the current fiber-aware guards it
        // does exactly that — a guard bare-suspends its loop fiber, the Revolt driver has no resumer, the
        // strand is abandoned and this suspension returns with tests missing. Fail loudly instead of
        // silently dropping tests (a green run that ran nothing is the worst outcome). This is the
        // PerCase blocker: it needs fiber-local guards, fixed on the main branch.
        $done = \count($results) + \count($errors);
        $done === \count($handlers) or throw new \RuntimeException(\sprintf(
            'Revolt PerCase lost %d of %d test(s): the event loop unwound before they finished — the '
            . 'fiber-aware guards deadlock against the Revolt driver. PerCase needs fiber-local guards.',
            \count($handlers) - $done,
            \count($handlers),
        ));

        $errors === [] or throw \reset($errors);

        \ksort($results);

        return \array_values($results);
    }
}
