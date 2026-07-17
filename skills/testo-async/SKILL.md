---
name: testo-async
description: Run Testo tests as fibers/coroutines on the Revolt event loop with #[Async] (and the case-level #[Concurrent]). Use when a test must await real async work — amphp libraries, Revolt timers/streams, Future::await(), or plain-fiber code that suspends — or when the user mentions "async test", "coroutine", "event loop", "revolt", "amphp", or "await" in a test.
---

# Async / coroutine tests in Testo

Provided by the `testo/async` plugin (ships with Testo). It runs a test inside a fiber driven by the process-global **Revolt** event loop, so the test may `await` real async work without blocking the process.

Fetch `https://php-testo.github.io/llms.txt` for the current attribute namespaces and parameters before writing code.

| Attribute | Level | Purpose |
|---|---|---|
| `#[Async]` | method / class | Run the test as its own isolated coroutine on the event loop. |
| `#[Concurrent(Strategy)]` | class | Case-level scheduling entry point. **v1: `Strategy::Sequential` only, as a pass-through.** |

## `#[Async]` — the one you almost always want

```php
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
use Testo\Concurrent;
use Testo\Async\Strategy;

#[Test]
#[Concurrent(Strategy::Sequential)]
final class ClientTest { /* ... */ }
```

- `Strategy` enum (`Testo\Async\Strategy`): `Sequential`, `RoundRobin`, `Random`.
- **v1 reality**: only `Sequential` is implemented, and it is a **pass-through** (tests run one after another — Testo's default order). A method-level `#[Async]` inside a `#[Concurrent]` case still gets its own coroutine and wins locally.
- `RoundRobin` / `Random` throw a `LogicException` ("not implemented yet") rather than silently degrading — do not use them yet.

## Pitfalls

- **`#[Async]` is required to await.** A test that calls `Future::await()` / `EventLoop::getSuspension()->suspend()` without `#[Async]` will still run on the main fiber and may deadlock or terminate the loop without resuming. Add `#[Async]`.
- **Do not `await` inside a `#[Concurrent]` case (without `#[Async]` on the method) yet.** v1 sequential is a pass-through; a real shared-loop run is blocked on making Testo's fiber-aware scoped-state guards (assertion collector, messenger scope) Revolt-compatible. Await through a per-method `#[Async]` instead.
- **One coroutine per executed instance.** `#[Async]` sits closest to the test, so `#[Retry]`/`#[Repeat]`/data-provider wrap it — each attempt / dataset gets a fresh loop drive.
- **Leaked watchers are a smell.** A `repeat()`/timer/stream watcher left enabled when the test returns lives on the process-global loop; cancel what you register.
- Revolt is the reactor only — futures, channels, HTTP clients live in **amphp** on top of it. Pull in the amphp package you need as a dev dependency.
