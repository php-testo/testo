---
name: testo-fiber
description: Run Testo tests as cooperatively-scheduled plain PHP fibers with #[RunInFiber] — for fiber/coroutine code that suspends with \Fiber::suspend() and for interleaving a case's tests to shake out order-dependent races. Use when a test drives fibers, yields cooperatively, or needs deterministic interleaving. For real async I/O (amphp, Revolt timers/streams, Future::await()) use the testo/bridge-revolt #[RunInRevolt] attribute instead.
---

# Fiber / coroutine tests in Testo

Provided by the `testo/fiber` plugin (ships with Testo). It runs tests inside plain PHP fibers driven by Testo's **own cooperative scheduler** — no event loop, no preemption. Switching happens only where the running fiber calls `\Fiber::suspend()`.

Fetch `https://php-testo.github.io/llms.txt` for the current attribute namespaces and parameters before writing code.

| Attribute | Level | Purpose |
|---|---|---|
| `#[RunInFiber]` | method | Run this test in its own fiber (so cooperative `\Fiber::suspend()` works). |
| `#[RunInFiber(Schedule)]` | class | Schedule the case's tests: `Solo` (default), `RoundRobin` / `Random` cooperative interleaving. |

Everything lives in the `Testo\Fiber\` namespace (`Testo\Fiber\RunInFiber`, `Testo\Fiber\Schedule`).

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
use Testo\Fiber\Schedule;

#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class RaceTest
{
    private static array $shared = [];
    public function writer(): void { self::$shared[] = 1; \Fiber::suspend(); self::$shared[] = 2; }
    public function reader(): void { \Fiber::suspend(); Assert::count(self::$shared, 1); }
}
```

- `Schedule` enum (`Testo\Fiber\Schedule`): `Solo` (each test in its own fiber, to completion, no interleave — default), `RoundRobin` (one step per ready test each round), `Random` (a random ready test each round — non-seeded, not reproducible yet).
- `RoundRobin` / `Random` interleave the case's tests on plain fibers, switching only where a fiber calls `\Fiber::suspend()`. Put a `\Fiber::suspend()` where a context switch should be allowed (in real use, the async driver the test exercises does this). Per-test assertion state stays isolated across the interleave.
- Reports stay readable while tests interleave: each test carries a `TestIdentity`, so the terminal renders every test — its batch node, data sets, streamed `-vv` output and result line — as one contiguous block instead of splicing them together, and `--teamcity` stamps a per-test `flowId`. Blocks appear in the order tests finish, so a test that is not the one currently streaming shows up once it completes.

## Pitfalls

- **This is NOT real async I/O.** There is no event loop. Awaiting a timer, socket, or `Future` (amphp/Revolt) does **not** work under `#[RunInFiber]` — a bare `\Fiber::suspend()` waiting on external I/O has no resumer. For real async work use the `testo/bridge-revolt` `#[RunInRevolt]` attribute (runs the test on the Revolt event loop).
- **Switching is cooperative — no preemption.** A test that never calls `\Fiber::suspend()` never yields; interleaving only happens at those suspension points.
- **Only suspend under `#[RunInFiber]`.** A `\Fiber::suspend()` on the main fiber (a test without `#[RunInFiber]`) fatals — there is no scheduler to resume it. Guard driver code with `\Fiber::getCurrent() !== null` if it may run outside a fiber.
- **One fiber per test, not per attempt.** `#[RunInFiber]` sits *outside* the data provider and the retry/repeat wrappers, so a data-driven or retried test runs all its datasets / attempts inside one fiber. That ordering is what puts the scoped-state guards (assertions, messenger, container) *inside* the fiber, where they swap each test's state in and out at every switch.
- **A fiber your test spawns inherits the test's scoped state.** The guards keep the *running* test's state active — swapped in while the test holds the floor, out while it is parked — so `Assert::*` calls, messenger writes and container lookups made inside a fiber your test body creates and drives are attributed to that test, at any nesting depth and under any `Schedule`. A fiber you spawn but hand to someone else to resume later (after your test finished) has no such guarantee — don't let helper fibers outlive the test.
