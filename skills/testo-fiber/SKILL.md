---
name: testo-fiber
description: Run Testo tests as cooperatively-scheduled plain PHP fibers with #[RunInFiber] — for fiber/coroutine code that suspends with \Fiber::suspend() and for interleaving a case's tests to shake out order-dependent races. Coroutine::spawn()/await()/concurrently() add coroutines to the running test's schedule. Use when a test drives fibers, yields cooperatively, spawns concurrent coroutines, or needs deterministic interleaving. For real async I/O (amphp, Revolt timers/streams, Future::await()) use the testo/bridge-revolt #[RunInRevolt] attribute instead.
---

# Fiber / coroutine tests in Testo

Provided by the `testo/fiber` plugin (ships with Testo). It runs tests inside plain PHP fibers driven by Testo's **own cooperative scheduler** — no event loop, no preemption. Switching happens only where the running fiber calls `\Fiber::suspend()`.

Fetch `https://php-testo.github.io/llms.txt` for the current attribute namespaces and parameters before writing code.

| API | Level | Purpose |
|---|---|---|
| `#[RunInFiber]` | method | Run this test in its own fiber (so cooperative `\Fiber::suspend()` works). |
| `#[RunInFiber(Schedule)]` | class | Schedule the case's tests: `Solo` (default), `RoundRobin` / `Random` cooperative interleaving. |
| `Coroutine::spawn(fn)` | in test | Add a coroutine to the running test's schedule; returns a `Coroutine` handle. |
| `$handle->await()` | in test | Park the caller until the coroutine finishes; return its result or rethrow its failure. |
| `Coroutine::concurrently(...)` | in test | Spawn several closures/fibers and wait for all; results keyed like the arguments. |

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

## `Coroutine` — spawn concurrent coroutines inside a test

Every `#[RunInFiber]` test runs inside its own **coroutine scope**: the test body is the scope's first coroutine, and `Coroutine::spawn()` adds more to the same round-robin schedule. Coroutines interleave with the body (and each other) at every `\Fiber::suspend()`, and — under a class-level `#[RunInFiber]` — the whole scope keeps interleaving with the case's other tests.

```php
use Testo\Assert;
use Testo\Fiber\Coroutine;
use Testo\Fiber\RunInFiber;
use Testo\Test;

#[Test]
#[RunInFiber]
public function pingPong(): void
{
    $server = Coroutine::spawn(fn(): string => $this->acceptAndEcho());   // Closure or unstarted \Fiber
    $client = Coroutine::spawn(fn(): string => $this->connectAndSend('ping'));

    Assert::same($client->await(), 'pong');   // parks the body; others keep running
    Assert::true($server->isFinished());

    // Sugar: spawn + await all; named arguments key the results.
    $r = Coroutine::concurrently(pull: fn() => $q->pull(), push: fn() => $q->push(1));
    Assert::same($r['push'], 1);
}
```

Rules (verified against `plugin/fiber/src/Coroutine.php`):

- `spawn()` needs an active scope — outside `#[RunInFiber]` it throws a `LogicException`. Assertions, messages **and coverage** inside a coroutine are attributed to the test that spawned it: the scope runs inside both the scoped-state guards and the test's coverage window.
- **The scope is structured**: the test is not finished until every coroutine it spawned is. Coroutines still pending when the body returns keep being driven; if the body *fails*, they are cancelled — a `Testo\Fiber\Exception\CancelledException` is thrown into each pending fiber (its `finally` blocks run; don't swallow it). Awaiting a cancelled coroutine rethrows the `CancelledException` (unwrapped — it is a control signal, not a coroutine failure).
- **Coroutine failures always arrive wrapped in `Testo\Fiber\Exception\CompositeException`** — even a single one — whether rethrown by `await()` / `concurrently()` or reported at scope close for a coroutine nobody awaited (that marks the test `Error`). The body's own throw stays unwrapped, so `#[ExpectException]` on it works as usual; expect `CompositeException` when the throw comes from a coroutine.
- An await cycle is detected and broken with a `Testo\Fiber\Exception\DeadlockException` raised at the first doomed `await()` — even a cycle spanning several tests' scopes (handles shared under a class-level `#[RunInFiber]`). A bare `\Fiber::suspend()` loop waiting for something that never happens is **not** detected.
- `concurrently()` waits for *all* its coroutines even after one fails, then bundles every failure into one composite whose `$errors` are keyed like the arguments — symmetric to the results, so a named argument's failure is found under its name.

## Pitfalls

- **This is NOT real async I/O.** There is no event loop. Awaiting a timer, socket, or `Future` (amphp/Revolt) does **not** work under `#[RunInFiber]` — a bare `\Fiber::suspend()` waiting on external I/O has no resumer. For real async work use the `testo/bridge-revolt` `#[RunInRevolt]` attribute (runs the test on the Revolt event loop).
- **Switching is cooperative — no preemption.** A test that never calls `\Fiber::suspend()` never yields; interleaving only happens at those suspension points.
- **Only suspend under `#[RunInFiber]`.** A `\Fiber::suspend()` on the main fiber (a test without `#[RunInFiber]`) fatals — there is no scheduler to resume it. Guard driver code with `\Fiber::getCurrent() !== null` if it may run outside a fiber.
- **One fiber per test, not per attempt.** `#[RunInFiber]` sits *outside* the data provider and the retry/repeat wrappers, so a data-driven or retried test runs all its datasets / attempts inside one fiber. That ordering is what puts the scoped-state guards (assertions, messenger, container) *inside* the fiber, where they swap each test's state in and out at every switch.
- **Coverage is per test across an interleave, but `Branch`/`Path` needs Xdebug ≥ 3.4.5.** The coverage window is closed around every fiber switch, so each test's report lines are its own. Both levels above `Line` enable Xdebug branch analysis, which corrupts memory in older builds when the window lives inside a fiber; on Xdebug < 3.4.5 such a test is stopped with `BranchCoverageUnsafeInFiber` instead of crashing the process. See the `testo-coverage` skill.
- **A fiber your test spawns inherits the test's scoped state.** The guards keep the *running* test's state active — swapped in while the test holds the floor, out while it is parked — so `Assert::*` calls, messenger writes and container lookups made inside a fiber your test body creates and drives are attributed to that test, at any nesting depth and under any `Schedule`. A fiber you spawn but hand to someone else to resume later (after your test finished) has no such guarantee — don't let helper fibers outlive the test.
