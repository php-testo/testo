<?php

declare(strict_types=1);

namespace Testo\Async;

use Testo\Async\Internal\RunInCoroutineInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Runs a single test inside its own isolated coroutine driven by the Revolt event loop, so the test
 * body may suspend on plain fibers and await real async work (timers, streams, amphp libraries) and
 * resume without blocking the process.
 *
 * ```php
 *  use Testo\Async\RunInCoroutine;
 *
 *  #[RunInCoroutine]
 *  public function fetchesConcurrently(): void
 *  {
 *      // await real async work here — the loop is running for this test
 *  }
 * ```
 *
 * **The window is isolated.** The test's fiber is driven to completion on its own loop run and no
 * other test overlaps it. From the outside `#[RunInCoroutine]` stays an ordinary blocking call —
 * nothing suspends past it. Do not combine `#[RunInCoroutine]` with a non-sequential {@see Concurrent}
 * on the same case: that scheduler interleaves plain fibers and does not drive the Revolt loop.
 *
 * Placed on a class it becomes the default for every test method; a method-level attribute overrides
 * it.
 *
 * The attribute is self-wiring: it is {@see Interceptable}, so {@see RunInCoroutineInterceptor} is
 * inserted into the pipeline only for tests that carry it — no plugin registration needed.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[FallbackInterceptor(RunInCoroutineInterceptor::class)]
final readonly class RunInCoroutine implements Interceptable {}
