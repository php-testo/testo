<?php

declare(strict_types=1);

namespace Testo;

use Testo\Async\Internal\ConcurrentRunInterceptor;
use Testo\Async\Strategy;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Schedules all tests of a test case according to a {@see Strategy}.
 *
 * ```php
 *  use Testo\Concurrent;
 *  use Testo\Async\Coroutine;
 *  use Testo\Async\Strategy;
 *
 *  #[Concurrent(Strategy::RoundRobin)]
 *  final class RaceTest
 *  {
 *      public function writer(): void { $this->shared[] = 1; Coroutine::reschedule(); $this->shared[] = 2; }
 *      public function reader(): void { Coroutine::reschedule(); Assert::count($this->shared, 1); }
 *  }
 * ```
 *
 * - `Strategy::Sequential` (default) — Testo's default order; a pass-through.
 * - `Strategy::RoundRobin` / `Strategy::Random` — interleave the case's tests on plain fibers,
 *   switching at {@see Coroutine::reschedule()} points, to shake out order-dependent races.
 *
 * The interleaving runs on plain fibers (not Revolt), so switching is **cooperative** — only
 * `reschedule()` points can hand control to another test. Awaiting real async work (Revolt/amphp)
 * inside an interleaved test is unsupported; use {@see Async} for that. A per-method `#[Async]` under a
 * non-sequential `#[Concurrent]` is likewise unsupported.
 *
 * The attribute is self-wiring: it is {@see Interceptable}, so {@see ConcurrentRunInterceptor} wraps
 * the case run only for cases that carry it — no plugin registration needed.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(ConcurrentRunInterceptor::class)]
final readonly class Concurrent implements Interceptable
{
    /**
     * @param Strategy $strategy How the case's tests are scheduled.
     */
    public function __construct(
        public Strategy $strategy = Strategy::Sequential,
    ) {}
}
