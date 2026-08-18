---
name: testo-flaky-tests
description: Stabilize flaky Testo tests with #[Retry] or stress-verify with #[Repeat]. Use when the user mentions "flaky test", "intermittent failure", "retry", "rerun", or asks to "verify a fix sticks" by running the test many times. Also use to mark a test flaky for reporting.
---

# Flaky and repeated tests in Testo

Two attributes, two different jobs. Don't mix them up.

| Attribute | Purpose |
|---|---|
| `#[Retry]` | On failure, run again up to `maxAttempts` total. Pass = the run is green. By default the run is **also marked flaky** if a retry was needed. |
| `#[Repeat]` | Always run `times` runs in total. Used to *surface* flakiness or stress-verify a fix. |

Both should be a **last resort** — first investigate the root cause (shared global state, time/timezone, ordering, randomness, network). Surface this to the user before reaching for `#[Retry]`.

Fetch `https://php-testo.github.io/llms.txt` for the current attribute namespaces and parameters.

## `#[Retry]` — make a known-flaky test green-ish

```php
use Testo\Retry;
use Testo\Test;

#[Test]
#[Retry(maxAttempts: 3)]
public function pollsExternalService(): void
{
    $response = $this->api->fetch();
    Assert::same($response->status, 200);
}
```

Constructor (verified against `plugin/retry/Retry.php`):

```php
public function __construct(
    public int $maxAttempts = 3,
    public bool $markFlaky  = true,   // ← default ON
) {}
```

- `maxAttempts` is the **total** number of attempts (3 = first run + up to 2 retries).
- `markFlaky` is **on by default** — when a retry was needed, the run is reported flaky even though it eventually passed. **Do not disable** this unless the user explicitly asks: silent retries are how flakiness rots a suite.
- Valid targets: method, function, **class** (`TARGET_CLASS` is allowed). Apply at the class level only when every test in it is independently flaky for the same external reason — that's rare; usually it's a smell.

## `#[Repeat]` — run a test N times unconditionally

```php
use Testo\Repeat;

#[Test]
#[Repeat(times: 50)]
public function concurrentInsertNeverDeadlocks(): void
{
    $this->runConcurrentInsert();
}
```

Constructor (verified against `plugin/repeat/Repeat.php`):

```php
public function __construct(
    public int  $times       = 2,    // total runs, NOT additional repetitions
    public int  $maxFailures = 0,    // failures tolerated before the whole loop fails
    public bool $markFlaky   = true, // ← default ON, reports flaky if any run failed but stayed within maxFailures
) {}
```

- `times` is the **total** number of runs. `#[Repeat(times: 3)]` runs the test three times. It is **not** "additional repetitions on top of one run".
- `maxFailures` defaults to `0` — any single failure fails the whole loop.
- Combining with `#[Retry]`: Repeat runs *inside* Retry — each retry attempt re-runs the full repeat cycle. Possible, but the semantics are subtle; surface it to the user before suggesting both.

Use cases:

- Verifying a fix for a flaky test actually sticks (`#[Repeat(times: 100)]`, run locally, remove before merging).
- Probabilistic / concurrency / randomness tests where one run is not enough evidence.

Don't ship `#[Repeat(times: 50)]` long-term on a fast suite — CI cost adds up. Remove or scale down once the fix has been validated.

## Decision flow

1. Is the test failing intermittently in CI?
   - **Yes** → investigate root cause first. If genuinely external (DNS, third-party API) → `#[Retry(maxAttempts: 3)]` (`markFlaky` is already on by default).
   - **No, but I want to verify a fix** → `#[Repeat(times: N)]`, run locally, remove before merging.
2. Is the test deterministic but slow / probabilistic by nature (sampling, fuzz)?
   - Use `#[Repeat]`, never `#[Retry]`.
3. Is the flakiness from shared state inside the suite (ordering)?
   - Don't reach for either attribute. Fix isolation (lifecycle hooks, fresh fixtures).

## Pitfalls

- **Don't disable `markFlaky`** on `#[Retry]` / `#[Repeat]` (it defaults to `true`). Setting `markFlaky: false` is **silent rot** — a flaky test that retries to green hides the underlying defect. Only flip it off when the user explicitly asks.
- **`Repeat(times: N)` is total runs, not extra runs.** `Repeat(times: 1)` runs the test once, `Repeat(times: 3)` runs it three times total — `N` is the run count, not a number of *additional* runs on top of the first.
- Combining `#[Retry]` with `#[Repeat]` is allowed: Repeat runs **inside** Retry (each retry attempt re-runs the full repeat cycle). Only suggest both when the user genuinely wants that nesting.
- A test with `Expect::exception(...)` and `#[Retry]` is almost always wrong — expected exceptions are deterministic by design.
- Don't use retries to paper over network calls in unit tests — replace the dependency with a fake instead.
- Throwing `SkipTest` / `CancelTest` from the body short-circuits both `#[Retry]` and `#[Repeat]` — the loop stops immediately and the result keeps the `Skipped` / `Cancelled` status. That's intentional (skipping isn't a failure to retry against), but worth knowing when a "flaky" test is actually skipping on some runs.
