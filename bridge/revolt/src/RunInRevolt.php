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
 * Applied to a class it drives every test of the case — but **one at a time**, each run to completion
 * before the next enters the loop. Only the test body goes on the loop; Testo's scoped-state guards open
 * their scopes outside it, so for the whole dispatch their active state is this test's — and a coroutine
 * the test spawns (`EventLoop::queue()`, an `async()` call) reads it like the body does: its assertions
 * and output land on the right test however deep it nests. To interleave whole tests with each other, use
 * `testo/fiber`'s `#[RunInFiber]` schedules, which run on plain fibers Testo itself drives.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
#[FallbackInterceptor(RunInRevoltInterceptor::class)]
final readonly class RunInRevolt implements Interceptable {}
