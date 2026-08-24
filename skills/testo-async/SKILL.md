---
name: testo-async
description: 'Run async Testo tests: #[RunInFiber] (testo/fiber — cooperatively-scheduled plain fibers, deterministic interleaving, Coroutine::spawn) and #[RunInRevolt] (testo/bridge-revolt — real async I/O on the Revolt event loop: amphp, timers/streams, Future::await()). Use when a test drives fibers or coroutines, awaits async work, spawns concurrent coroutines, or needs interleaving to shake out order-dependent races — or when the user mentions "async test", "fiber", "coroutine", "event loop", "revolt", "amphp", or "await" in a test.'
---

# Async tests in Testo

Two attributes, two different jobs — **concurrency** vs **asynchrony**:

| You are testing | Attribute | Machine |
|---|---|---|
| **Concurrency**: cooperative code that suspends with `\Fiber::suspend()`; races between a case's tests | `#[RunInFiber]` | Testo's **own cooperative scheduler** on plain fibers — no event loop, no preemption. Ships with Testo (`testo/fiber` plugin). |
| **Asynchrony**: real async I/O — amphp v3 libraries, Revolt timers/streams, `Future::await()` | `#[RunInRevolt]` | The process-global **Revolt event loop** owns the fiber. Separate package: `composer require --dev testo/bridge-revolt`. |

They don't substitute for each other: `#[RunInFiber]` runs no event loop (a suspension waiting on I/O has no resumer), and `#[RunInRevolt]` doesn't interleave tests with each other.

Fetch `https://php-testo.github.io/llms.txt` for the current attribute namespaces and parameters before writing code.

## `#[RunInFiber]` — plain fibers, Testo-driven

| API | Level | Purpose |
|---|---|---|
| `#[RunInFiber]` | method / function | Run this test in its own fiber (so cooperative `\Fiber::suspend()` works). |
| `#[RunInFiber(Schedule)]` | class | Schedule the case's tests: `Solo` (default), `RoundRobin` / `Random` cooperative interleaving. |
| `Coroutine::spawn(fn)` | in test | Add a coroutine to the running test's scope; returns a `Coroutine` handle. |
| `$handle->await()` | in test | Park the caller until the coroutine finishes; return its result or rethrow its failure. |
| `Coroutine::concurrently(...)` | in test | Spawn several closures/fibers and wait for all; results keyed like the arguments. |

Everything lives in the `Testo\Fiber\` namespace (`Testo\Fiber\RunInFiber`, `Testo\Fiber\Schedule`, `Testo\Fiber\Coroutine`).

### Run a test in a fiber

```php
use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Test;

#[Test]
#[RunInFiber]
public function runsCooperativeDriver(): void
{
    $job = new StepwiseJob();
    $job->run();               // calls \Fiber::suspend() internally between steps

    Assert::same($job->completed, ['fetch', 'process', 'store']);
}
```

- Constructor: `Schedule $schedule = Schedule::Solo` (positional; class-level only — ignored on a single test).
- On a **method or function** it wraps just that test in a fiber. On a **class** it schedules every test of the case per the `Schedule`. The levels don't conflict: with the attribute already on the class, a method-level one doesn't create a second fiber.

### `#[RunInFiber(Schedule)]` — case-level interleaving

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

- `Schedule` enum: `Solo` (each test to completion, one after another — default), `RoundRobin` (one step per ready test each round, deterministic), `Random` (a random ready test each step — non-seeded, not reproducible yet).
- Interleaving is how you catch races: while one test is suspended part-way through its work, another inspects the shared state in an intermediate, not-yet-consistent form. Switching happens only where a fiber calls `\Fiber::suspend()` — put one where a context switch should be allowed.
- Per-test state (assertions, messages, container scopes, coverage) is swapped in and out at every fiber switch, so interleaved tests each keep their own history. Terminal output stays unjumbled too: each test renders as one contiguous block, in finish order.

### `Coroutine` — several participants inside one test

For a producer and a consumer, a client and a server — several participants within a single test. Every `#[RunInFiber]` test has its own **coroutine scope**: the test body is the scope's first coroutine, and `Coroutine::spawn()` adds more. Inside a scope the schedule is always round-robin — the `Schedule` strategy governs only how *tests* interleave; and between rounds the scope yields outward, so one test's coroutines don't hog the case.

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

    // Sugar: spawn + await all; named arguments key the results (and the errors).
    $r = Coroutine::concurrently(pull: fn() => $q->pull(), push: fn() => $q->push(1));
    Assert::same($r['push'], 1);
}
```

Rules:

- `spawn()` throws a `LogicException` when there is no scope (a test without `#[RunInFiber]`), when the given `\Fiber` has already started, or when the scope is already closing (e.g. spawning from the `finally` of a coroutine being cancelled). It may be called from anywhere inside the test, including from another coroutine — all land in the same scope.
- **The scope is structured**: a coroutine can't outlive its test. Coroutines still pending when the body returns are driven to completion; if the body *fails*, they are cancelled — a `Testo\Fiber\Exception\CancelledException` is thrown at each pending suspension point (its `finally` blocks run; don't swallow it and suspend again). Awaiting a cancelled coroutine rethrows the `CancelledException` — a cancelled coroutine has no result.
- **A coroutine can't be forgotten.** One you spawned but never awaited still runs to completion, and its failure surfaces at the test level: an otherwise-passing test gets `Error` status. If the body failed too, the body keeps its own status and its error goes first in the composite.
- **Coroutine failures always arrive wrapped in `Testo\Fiber\Exception\CompositeException`** — even a single one. The originals sit in `$errors` (keyed like the arguments for `concurrently()`), and the earliest is mirrored into `getPrevious()` so error output shows the root cause. `await()` marks the rethrown error as handled, so the scope won't surface it again. The body's own throw stays unwrapped when the coroutines ran cleanly — `#[ExpectException]` on the body works as usual; expect `CompositeException` when the throw comes from a coroutine.
- **`concurrently()` drives all its coroutines to completion even after one fails**, then bundles every failure into one composite keyed like the results.
- An await cycle (A awaits B awaits C awaits A) is detected and broken with a `Testo\Fiber\Exception\DeadlockException` thrown from the first doomed `await()` — even a cycle spanning several tests' scopes (handles shared under a class-level `#[RunInFiber]`). Only `await()` is visible to the detector: a bare `\Fiber::suspend()` loop waiting for an event that never comes just hangs.

## `#[RunInRevolt]` — real async I/O on the Revolt loop

`Testo\Bridge\Revolt\RunInRevolt` (no parameters; targets class, method, and function) runs the test body on the process-wide Revolt event loop, where it can await asynchronous work — `Future::await()`, `Amp\delay()`, byte streams, any amphp v3 library. From the outside the call is still an ordinary blocking one: the pipeline waits for the test to finish.

```php
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Test;
use Revolt\EventLoop;

#[Test]
#[RunInRevolt]
public function resolvesAfterDelay(): void
{
    $suspension = EventLoop::getSuspension();
    EventLoop::delay(0.1, static fn() => $suspension->resume('ready'));

    Assert::same($suspension->suspend(), 'ready'); // the loop keeps spinning while we wait
}
```

- **Suspend only through a Revolt `Suspension`** tied to a loop event (a timer, I/O) — nothing on the loop will ever resume a bare `\Fiber::suspend()`.
- Applied to a **class** it sends every test of the case through the loop — but **one at a time**, each fully finished before the next enters. Tests never share a loop run: Revolt resumes its fibers directly, bypassing Testo's state guards, so concurrent tests would bleed assertions, messages and coverage into each other. Interleaving tests is `#[RunInFiber]`'s job.
- Only the test **body** goes on the loop. Data providers, retries and Testo's own plumbing run outside it; each dataset or retry attempt is dispatched as a loop run of its own.
- Coroutines the body spawns (`EventLoop::queue()`, an `async()` call) read the running test's scoped state: their assertions and output land on the right test, however deep they nest.

## Pitfalls

- **`#[RunInRevolt]` is required to await.** `Future::await()` and `EventLoop\Suspension` only work on the Revolt loop; without the attribute the test runs on the main fiber and may deadlock or die with "Event loop terminated without resuming the current suspension".
- **`\Fiber::suspend()` outside a fiber fails the test.** Without `#[RunInFiber]` the body runs in `{main}` (`\Fiber::getCurrent()` is `null`), and suspending throws a `\FiberError` ("Cannot suspend outside of a fiber"). Guard driver code with `\Fiber::getCurrent() !== null` if it may run outside a fiber.
- **Switching under `#[RunInFiber]` is cooperative — no preemption.** A test that never suspends never yields; there is no event loop watching timers, sockets, or I/O readiness.
- **Datasets and `#[Retry]`/`#[Repeat]` attempts never interleave.** Only tests interleave with each other; within one test they run sequentially — all datasets run the same code and would conflict over the same resources with no concurrency win.
- **Under `#[RunInFiber]`, collect coverage at `Line` level only.** `Branch`/`Path` turn on Xdebug's branch analysis, which corrupts memory and crashes the process when it runs inside a fiber — the bug persists in current Xdebug builds (reproduced on 3.5.3). Testo tries to stop such a test with `BranchCoverageUnsafeInFiber`, but the guard is tied to the Xdebug version and doesn't fire on every build — don't rely on it.
- **Don't let helper fibers outlive the test.** State attribution holds while the test runs — for its coroutines and for fibers it drives itself, at any nesting depth. A fiber handed to someone else to resume after the test finished has no such guarantee.
- **Leaked watchers are a smell.** A `repeat()`/timer/stream watcher left enabled when a `#[RunInRevolt]` test returns lives on the process-global loop; cancel what you register.
- **Revolt is the reactor only** — futures, channels, HTTP clients live in **amphp** on top of it. Pull in the amphp package you need as a dev dependency.
