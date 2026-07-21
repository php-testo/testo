<?php

declare(strict_types=1);

namespace Testo\Fiber;

use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Runs tests inside plain PHP fibers driven by Testo's own cooperative scheduler — so a test may
 * suspend (`\Fiber::suspend()`, {@see Coroutine::reschedule()}) and be resumed, and several tests of
 * a case may be interleaved to shake out order-dependent races.
 *
 * ```php
 *  use Testo\Fiber\RunInFiber;
 *  use Testo\Fiber\Coroutine;
 *  use Testo\Fiber\Schedule;
 *
 *  #[RunInFiber(Schedule::RoundRobin)]
 *  final class RaceTest
 *  {
 *      public function writer(): void { self::$shared[] = 1; Coroutine::reschedule(); self::$shared[] = 2; }
 *      public function reader(): void { Coroutine::reschedule(); Assert::count(self::$shared, 1); }
 *  }
 * ```
 *
 * Levels:
 * - on a **method** — that test runs in its own fiber (the {@see Schedule} is irrelevant for one test);
 * - on a **class** — every test of the case is scheduled per {@see Schedule}: `OneByOne` (each test in
 *   its own fiber, one after another — the default), or `RoundRobin` / `Random` cooperative interleaving.
 *
 * Switching is **cooperative** and happens only at `\Fiber::suspend()` / `Coroutine::reschedule()`
 * points — Testo's scheduler owns the fibers, there is no event loop and no preemption. This is for
 * fiber-based/cooperative code and race hunting, **not** for real async I/O: awaiting a timer, socket
 * or `Future` needs the Revolt event loop — use the `testo/bridge-revolt` `#[RunInRevolt]` attribute.
 *
 * Self-wiring: it is {@see Interceptable}, so {@see RunInFiberInterceptor} is inserted only for tests
 * that carry it — no plugin registration needed.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[FallbackInterceptor(RunInFiberInterceptor::class)]
final readonly class RunInFiber implements Interceptable
{
    /**
     * @param Schedule $schedule How the case's tests are scheduled (class-level only; ignored on a
     *        single method).
     */
    public function __construct(
        public Schedule $schedule = Schedule::OneByOne,
    ) {}
}
