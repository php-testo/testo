---
name: testo-async
description: Run Testo tests as fibers/coroutines on the Revolt event loop with #[Async] (and the case-level #[Concurrent]). Use when a test must await real async work — amphp libraries, Revolt timers/streams, Future::await(), or plain-fiber code that suspends — or when the user mentions "async test", "coroutine", "event loop", "revolt", "amphp", or "await" in a test.
---

# Async / coroutine tests in Testo

Provided by the `testo/async` plugin (ships with Testo). It runs a test inside a fiber driven by the process-global **Revolt** event loop, so the test may `await` real async work without blocking the process.

Fetch `https://php-testo.github.io/llms.txt` for the current attribute namespaces and parameters before writing code.

| Attribute | Level | Purpose |
|---|---|---|
| `#[Async]` | method / class | Run the test as its own isolated coroutine on the Revolt event loop (for real async I/O). |
| `#[Concurrent(Strategy)]` | class | Schedule the case's tests: `Sequential` (default order), or `RoundRobin` / `Random` cooperative interleaving. |

## `#[Async]` — the one you almost always want

```php
use Testo\Assert;
use Testo\Async;
use Testo\Test;
use Revolt\EventLoop;

#[Test]
#[Async]
public function fetchesConcurrently(): void
{
    $suspension = EventLoop::getSuspension();
    EventLoop::delay(0.1, static fn() => $suspension->resume('ready'));

    Assert::same($suspension->suspend(), 'ready'); // the loop runs while we wait
}
```

- Without `#[Async]` a test runs synchronously on the main fiber. Attaching it launches the body as a coroutine and drives the Revolt loop until the test completes — so `Future::await()`, `Amp\delay()`, byte streams, and any amphp v3 library work inside the test.
- The window is **isolated**: the test's fiber runs to completion and no other test overlaps it. From the outside `#[Async]` is still an ordinary blocking call — nothing suspends past it.
- Valid targets: method and class. On a class it is the default for every method; a method-level attribute overrides it.
- Constructor (verified against `plugin/async/Async.php`): no parameters.

## `#[Concurrent]` — case-level scheduling

```php
use Testo\Assert;
use Testo\Concurrent;
use Testo\Async\Coroutine;
use Testo\Async\Strategy;

#[Test]
#[Concurrent(Strategy::RoundRobin)]
final class RaceTest
{
    public function writer(): void { self::$shared[] = 1; Coroutine::reschedule(); self::$shared[] = 2; }
    public function reader(): void { Coroutine::reschedule(); Assert::count(self::$shared, 1); }
}
```

- `Strategy` enum (`Testo\Async\Strategy`): `Sequential` (default order, pass-through), `RoundRobin` (one step per ready test each round), `Random` (a random ready test each round — non-seeded, not reproducible yet).
- `RoundRobin` / `Random` interleave the case's tests on **plain fibers**, switching only at `Coroutine::reschedule()` points. Put `reschedule()` where a context switch should be allowed. Per-test assertion state stays isolated across the interleave.
- Interleaving is for shaking out order-dependent races in cooperative code — **not** for real async I/O.

## Pitfalls

- **`#[Async]` is required to await.** A test that calls `Future::await()` / `EventLoop::getSuspension()->suspend()` without `#[Async]` will still run on the main fiber and may deadlock or terminate the loop without resuming. Add `#[Async]`.
- **Interleaving is cooperative and Revolt-free.** Under `#[Concurrent(RoundRobin|Random)]` do NOT await real async work and do NOT put `#[Async]` on the methods — the scheduler runs plain fibers and switches only at `reschedule()`. For real async I/O use `#[Async]` (with `Strategy::Sequential`, or no `#[Concurrent]` at all). A shared Revolt-loop run for a whole case is still blocked on making Testo's fiber-aware guards Revolt-compatible.
- **`reschedule()` is a no-op outside interleaving.** Safe to call in any test; it only yields under `#[Concurrent(RoundRobin|Random)]`.
- **One coroutine per executed instance.** `#[Async]` sits closest to the test, so `#[Retry]`/`#[Repeat]`/data-provider wrap it — each attempt / dataset gets a fresh loop drive.
- **Leaked watchers are a smell.** A `repeat()`/timer/stream watcher left enabled when the test returns lives on the process-global loop; cancel what you register.
- Revolt is the reactor only — futures, channels, HTTP clients live in **amphp** on top of it. Pull in the amphp package you need as a dev dependency.
