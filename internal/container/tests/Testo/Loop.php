<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Testo;

use Revolt\EventLoop;

/**
 * Minimal Revolt harness for the container-scope tests.
 *
 * Runs a body as a coroutine on the process event loop and, from inside it, forces a real
 * round-trip through the Revolt driver. Deliberately independent of the Revolt bridge: these
 * tests probe how {@see \Internal\Container\ObjectContainer::scope()} behaves around a loop —
 * a scope opened outside it reaching everything on it, and nothing more.
 *
 * @internal
 */
final class Loop
{
    private function __construct() {}

    /**
     * Run $body as a single coroutine on the Revolt loop; return its value (or rethrow).
     *
     * `getSuspension()` binds to whatever calls this — `{main}` or a loop-driven fiber, so bodies may
     * nest `run()` calls. `suspend()` parks the caller (entering the loop when the caller is `{main}`)
     * while the queued microtask executes $body inside a loop-driven fiber of its own.
     *
     * @template T
     * @param \Closure(): T $body
     * @return T
     */
    public static function run(\Closure $body): mixed
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $body): void {
            try {
                $suspension->resume($body());
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
        });

        return $suspension->suspend();
    }

    /**
     * Inside a {@see run()} body: yield to the loop and resume on the next tick — a genuine
     * suspension through the Revolt driver, i.e. the point where a guard that hand-drives the
     * fiber collides with the driver resuming it.
     */
    public static function tick(): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::queue(static fn() => $suspension->resume());
        $suspension->suspend();
    }
}
