<?php

declare(strict_types=1);

namespace Testo\Async\Internal;

use Testo\Async\Strategy;

/**
 * Cooperative fiber scheduler for interleaving strategies of {@see \Testo\Concurrent}.
 *
 * Drives a set of test fibers to completion on **plain fibers** (no Revolt), switching between them
 * only at {@see \Testo\Async\Coroutine::reschedule()} points. This deliberately uses Testo's existing
 * fiber-aware guard protocol (each guard re-suspends to its parent and swaps scoped state around the
 * switch), so per-test assertion/messenger state stays isolated across the interleave — and it avoids
 * the Revolt driver-ownership conflict that blocks a shared event-loop run.
 *
 * @internal
 * @psalm-internal Testo\Async
 */
final class Scheduler
{
    private static int $depth = 0;

    /**
     * Whether a cooperative scheduler is currently driving (so `reschedule()` should actually yield).
     */
    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Drive the given test fibers to completion under a cooperative {@see Strategy}.
     *
     * - `RoundRobin`: one step per ready fiber each round, in order.
     * - `Random`: one step of a random ready fiber each round.
     *
     * @param list<\Fiber> $fibers
     * @return array<int, \Throwable> Throwables thrown by fibers, keyed by fiber index (others done).
     */
    public static function run(array $fibers, Strategy $strategy): array
    {
        ++self::$depth;
        $errors = [];
        try {
            $ready = \array_keys($fibers);
            while ($ready !== []) {
                // RoundRobin steps every ready fiber this round; Random steps one random ready fiber.
                $round = $strategy === Strategy::Random ? [$ready[\random_int(0, \count($ready) - 1)]] : $ready;
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
