<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Testo;

/**
 * Tiny cooperative fiber driver for the container-scope tests — no event loop, just plain fibers.
 *
 * {@see runInFiber()} drives one fiber and, for each value it suspends, calls a check that may throw
 * **back into** the fiber at that suspension point (to probe how a scope behaves when an exception is
 * injected mid-flight). {@see runSequence()} round-robins several fibers to completion so their scopes
 * genuinely interleave at each `\Fiber::suspend()`.
 *
 * Adapted from Spiral's Core scope fiber tests.
 *
 * @internal
 */
final class Fibers
{
    private function __construct() {}

    /**
     * Run $callable in a fiber; for each suspended value call $check (which may return a replacement
     * value or throw it back into the fiber). Returns the fiber's return value.
     *
     * @template TReturn
     * @param callable(): TReturn $callable
     * @param null|callable(mixed): mixed $check
     * @return TReturn
     */
    public static function runInFiber(callable $callable, ?callable $check = null): mixed
    {
        $fiber = new \Fiber($callable);
        $value = $fiber->start();
        while (!$fiber->isTerminated()) {
            if ($check !== null) {
                try {
                    $value = $check($value);
                } catch (\Throwable $e) {
                    $value = $fiber->throw($e);
                    continue;
                }
            }
            $value = $fiber->resume($value);
        }

        return $fiber->getReturn();
    }

    /**
     * Start every callable in its own fiber, then round-robin resume them to completion so they
     * interleave at each `\Fiber::suspend()`. Returns each fiber's return value, keyed by input order.
     *
     * @param callable(): mixed ...$callables
     * @return array<array-key, mixed>
     */
    public static function runSequence(callable ...$callables): array
    {
        $fibers = [];
        $results = [];
        foreach ($callables as $key => $callable) {
            $fibers[$key] = new \Fiber($callable);
            $results[$key] = null;
        }

        foreach ($fibers as $fiber) {
            $fiber->start();
        }

        while ($fibers !== []) {
            foreach ($fibers as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[$key] = $fiber->getReturn();
                    unset($fibers[$key]);
                    continue;
                }
                $fiber->resume();
            }
        }

        return $results;
    }
}
