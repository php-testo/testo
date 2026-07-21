---
name: testo-fiber
description: Run Testo tests as cooperatively-scheduled plain PHP fibers with #[RunInFiber] — for fiber/coroutine code that suspends (\Fiber::suspend(), Coroutine::reschedule()) and for interleaving a case's tests to shake out order-dependent races. Use when a test drives fibers, yields cooperatively, or needs deterministic interleaving. For real async I/O (amphp, Revolt timers/streams, Future::await()) use the testo/bridge-revolt #[RunInRevolt] attribute instead.
---

# Fiber / coroutine tests in Testo

Provided by the `testo/fiber` plugin (ships with Testo). It runs tests inside plain PHP fibers driven by Testo's **own cooperative scheduler** — no event loop, no preemption. Switching happens only at suspension points (`\Fiber::suspend()`, `Coroutine::reschedule()`).

Fetch `https://php-testo.github.io/llms.txt` for the current attribute namespaces and parameters before writing code.

| Attribute | Level | Purpose |
|---|---|---|
| `#[RunInFiber]` | method | Run this test in its own fiber (so cooperative `\Fiber::suspend()` / `Coroutine::reschedule()` works). |
| `#[RunInFiber(Schedule)]` | class | Schedule the case's tests: `Solo` (default), `RoundRobin` / `Random` cooperative interleaving. |

Everything lives in the `Testo\Fiber\` namespace (`Testo\Fiber\RunInFiber`, `Testo\Fiber\Schedule`, `Testo\Fiber\Coroutine`).

## `#[RunInFiber]` — run a test in a fiber

```php
use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Test;

#[Test]
#[RunInFiber]
public function drivesAFiber(): void
{
    $f = new \Fiber(function (): void { \Fiber::suspend(); /* ... */ });
    $f->start();
    Assert::false($f->isTerminated());
    $f->resume();
    Assert::true($f->isTerminated());
}
```

- On a **method** it wraps just that test in a fiber. On a **class** it wraps every test of the case, scheduled by the `Schedule`.
- Constructor (verified against `plugin/fiber/src/RunInFiber.php`): `Schedule $schedule = Schedule::Solo` (positional; class-level only — ignored on a single method).

## `#[RunInFiber(Schedule)]` — case-level interleaving

```php
use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Schedule;

#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class RaceTest
{
    private static array $shared = [];
    public function writer(): void { self::$shared[] = 1; Coroutine::reschedule(); self::$shared[] = 2; }
    public function reader(): void { Coroutine::reschedule(); Assert::count(self::$shared, 1); }
}
```

- `Schedule` enum (`Testo\Fiber\Schedule`): `Solo` (each test in its own fiber, to completion, no interleave — default), `RoundRobin` (one step per ready test each round), `Random` (a random ready test each round — non-seeded, not reproducible yet).
- `RoundRobin` / `Random` interleave the case's tests on plain fibers, switching only at `Coroutine::reschedule()` points. Put `reschedule()` where a context switch should be allowed. Per-test assertion state stays isolated across the interleave.

## Pitfalls

- **This is NOT real async I/O.** There is no event loop. Awaiting a timer, socket, or `Future` (amphp/Revolt) does **not** work under `#[RunInFiber]` — a bare `\Fiber::suspend()` waiting on external I/O has no resumer. For real async work use the `testo/bridge-revolt` `#[RunInRevolt]` attribute (runs the test on the Revolt event loop).
- **Switching is cooperative — no preemption.** A test that never suspends never yields; interleaving only happens at `\Fiber::suspend()` / `Coroutine::reschedule()` points.
- **`reschedule()` is a no-op outside a scheduler run.** Safe to call in any test; it only yields when the fiber scheduler is active (i.e. under `#[RunInFiber]`), and only interleaves under `RoundRobin` / `Random`.
- **One fiber per executed instance.** `#[RunInFiber]` sits closest to the test, so `#[Retry]` / `#[Repeat]` / data-provider wrap it — each attempt / dataset gets its own fiber.
