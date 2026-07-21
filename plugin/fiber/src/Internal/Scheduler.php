<?php

declare(strict_types=1);

namespace Testo\Fiber\Internal;

use Testo\Fiber\Schedule;

/**
 * Cooperative fiber scheduler for {@see \Testo\Fiber\RunInFiber}.
 *
 * Drives a set of test fibers to completion on **plain fibers** (no event loop), switching between
 * them only where the running fiber calls `\Fiber::suspend()`. This uses Testo's fiber-aware guard
 * protocol (each guard re-suspends to its parent and swaps scoped state around the switch), so
 * per-test assertion/messenger state stays isolated across an interleave.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
final class Scheduler
{
    private static int $depth = 0;

    /**
     * Whether a scheduler is currently driving (used by the interceptor to avoid re-wrapping a test
     * that is already being scheduled).
     */
    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Drive the given test fibers to completion under a {@see Schedule}.
     *
     * - `Solo`: run each fiber to completion (resuming its own suspends) before the next.
     * - `RoundRobin`: one step per ready fiber each round, in order.
     * - `Random`: one step of a random ready fiber each round.
     *
     * @param list<\Fiber> $fibers
     * @return array<int, \Throwable> Throwables thrown by fibers, keyed by fiber index (others done).
     */
    public static function run(array $fibers, Schedule $schedule): array
    {
        ++self::$depth;
        $errors = [];
        try {
            if ($schedule === Schedule::Solo) {
                foreach (\array_keys($fibers) as $i) {
                    // Drive this fiber to completion (resuming its own cooperative suspends) before
                    // moving on — no other fiber overlaps it.
                    while (!$fibers[$i]->isTerminated()) {
                        self::step($fibers, $i, $errors);
                    }
                }

                return $errors;
            }

            $ready = \array_keys($fibers);
            while ($ready !== []) {
                // RoundRobin steps every ready fiber this round; Random steps one random ready fiber.
                $round = $schedule === Schedule::Random ? [$ready[\random_int(0, \count($ready) - 1)]] : $ready;
                foreach ($round as $i) {
                    self::step($fibers, $i, $errors);
                }

                $ready = \array_values(\array_filter($ready, static fn(int $i): bool => !$fibers[$i]->isTerminated()));
            }
        } finally {
            --self::$depth;
        }

        return $errors;
    }

    /**
     * @param list<\Fiber> $fibers
     * @param array<int, \Throwable> $errors
     */
    private static function step(array $fibers, int $i, array &$errors): void
    {
        $fiber = $fibers[$i];
        if ($fiber->isTerminated()) {
            return;
        }

        try {
            $fiber->isStarted() ? $fiber->resume() : $fiber->start();
        } catch (\Throwable $e) {
            // The fiber is terminated by the throw; record it against its index for the caller.
            $errors[$i] = $e;
        }
    }
}
