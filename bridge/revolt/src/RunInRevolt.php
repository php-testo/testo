<?php

declare(strict_types=1);

namespace Testo\Bridge\Revolt;

use Testo\Bridge\Revolt\Internal\RunInRevoltInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Runs a single test on the process-global **Revolt** event loop, so the test body may `await`
 * real async work (timers, streams, `Future::await()`, amphp libraries) and resume without
 * blocking the process.
 *
 * ```php
 *  use Testo\Bridge\Revolt\RunInRevolt;
 *  use Revolt\EventLoop;
 *
 *  #[RunInRevolt]
 *  public function fetchesConcurrently(): void
 *  {
 *      $suspension = EventLoop::getSuspension();
 *      EventLoop::delay(0.1, static fn() => $suspension->resume('ready'));
 *      Assert::same($suspension->suspend(), 'ready'); // the loop runs while we wait
 *  }
 * ```
 *
 * Unlike the plain-fiber `#[RunInFiber]` (the `testo/fiber` plugin, where Testo's own scheduler drives
 * the fiber), here the **Revolt loop** owns the fiber, so suspension must go through a Revolt
 * `Suspension` bound to a watcher (I/O, timer) — a bare `\Fiber::suspend()` has no resumer on the loop.
 *
 * The {@see Strategy} chooses when the loop is entered — {@see Strategy::PerTest} (default, one test on
 * the loop at a time, inner to the guards) or {@see Strategy::PerCase} (the whole case on one loop run,
 * which needs fiber-local guards and currently deadlocks).
 *
 * **Current limitation:** a case's tests run on the loop one at a time, each to completion (guards stay
 * on their synchronous main-fiber path). Interleaving several guarded tests on one shared loop
 * ({@see Strategy::PerCase}) is blocked until Testo's fiber-aware scoped-state guards move to
 * fiber-local storage.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[FallbackInterceptor(RunInRevoltInterceptor::class)]
final readonly class RunInRevolt implements Interceptable
{
    public function __construct(
        public Strategy $strategy = Strategy::PerTest,
    ) {}
}
