<?php

declare(strict_types=1);

namespace Testo;

use Testo\Async\Internal\AsyncRunInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Runs a single test inside its own isolated coroutine driven by the Revolt event loop, so the test
 * body may suspend on plain fibers and await real async work (timers, streams, amphp libraries) and
 * resume without blocking the process.
 *
 * ```php
 *  use Testo\Async;
 *
 *  #[Async]
 *  public function fetchesConcurrently(): void
 *  {
 *      // await real async work here — the loop is running for this test
 *  }
 * ```
 *
 * **The window is isolated.** The test's fiber is driven to completion on its own loop run and no
 * other test overlaps it. This holds even under a class-level {@see Concurrent}: an `#[Async]` test is
 * pulled out of the shared interleaving and runs as its own solo, self-contained coroutine. From the
 * outside `#[Async]` stays an ordinary blocking call — nothing suspends past it.
 *
 * Placed on a class it becomes the default for every test method; a method-level attribute overrides
 * it.
 *
 * The attribute is self-wiring: it is {@see Interceptable}, so {@see AsyncRunInterceptor} is inserted
 * into the pipeline only for tests that carry it — no plugin registration needed.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[FallbackInterceptor(AsyncRunInterceptor::class)]
final readonly class Async implements Interceptable {}
