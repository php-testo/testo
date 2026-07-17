<?php

declare(strict_types=1);

namespace Testo;

use Testo\Async\Internal\ConcurrentRunInterceptor;
use Testo\Async\Strategy;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Runs all tests of a test case within a single shared Revolt event-loop run, according to a
 * scheduling {@see Strategy}. Tests share one loop lifetime for the whole case and may suspend/await.
 *
 * ```php
 *  use Testo\Concurrent;
 *  use Testo\Async\Strategy;
 *
 *  #[Concurrent(Strategy::Sequential)]
 *  final class ClientTest { ... }
 * ```
 *
 * Tests individually marked {@see Async} keep their own coroutine and win locally.
 *
 * The scheduling strategy is implemented by the plugin's scheduler, not by Revolt (Revolt exposes no
 * ordering knob): only cooperative suspension points can switch tests, and real-I/O awaits resume when
 * the reactor says so. **v1 implements {@see Strategy::Sequential} as a pass-through** (Testo's default
 * order); a single shared loop run and the interleaving strategies (RoundRobin/Random) arrive once the
 * fiber-aware scoped-state guards are made Revolt-compatible.
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
     * @param Strategy $strategy How the case's tests are scheduled on the shared loop.
     */
    public function __construct(
        public Strategy $strategy = Strategy::Sequential,
    ) {}
}
