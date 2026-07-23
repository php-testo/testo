<?php

declare(strict_types=1);

namespace Testo\Bridge\Revolt\Internal;

use Revolt\EventLoop;
use Testo\Bridge\Revolt\Strategy;
use Testo\Core\Context\TestResult;

/**
 * Drives a case's test handlers on the process-global **Revolt** event loop, one to completion at a
 * time.
 *
 * An invokable runner — set on {@see \Testo\Core\Context\CaseInfo::$batchRunner} by
 * {@see RunInRevoltInterceptor::runTestCase()} under {@see Strategy::PerCase}. Each handler is launched
 * as a microtask (its own loop fiber) and the calling fiber blocks on a
 * {@see \Revolt\EventLoop\Suspension} until it completes.
 *
 * The whole per-test pipeline — including Testo's fiber-aware scoped-state guards — runs inside the
 * loop fiber here, which the guards cannot yet cooperate with (see {@see Strategy::PerCase}); a
 * concurrent Revolt schedule is a follow-up gated on the fiber-local guard migration.
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
     * @param list<callable(): TestResult> $handlers
     * @return list<TestResult>
     */
    public function __invoke(array $handlers): array
    {
        $results = [];
        foreach ($handlers as $handler) {
            $results[] = self::runOnLoop($handler);
        }

        return $results;
    }
}
