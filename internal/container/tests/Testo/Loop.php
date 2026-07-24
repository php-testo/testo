<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Testo;

use Revolt\EventLoop;

/**
 * Minimal Revolt harness for the container-scope tests.
 *
 * Runs a body as a coroutine on the process event loop and, from inside it, forces a real
 * round-trip through the Revolt driver. Deliberately independent of the `testo/async` plugin:
 * these tests probe how {@see \Internal\Container\ObjectContainer::scope()} behaves under
 * Revolt — the exact guard/driver interaction the fiber-local migration must fix.
 *
 * @internal
 */
final class Loop
{
    /**
     * Run $body as a single coroutine on the Revolt loop; return its value (or rethrow).
     *
     * `getSuspension()` is taken on the caller (main) fiber, so `suspend()` runs the loop while
     * the queued microtask executes $body inside a loop-driven fiber.
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

    private function __construct() {}
}
