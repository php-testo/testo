---
name: testo-benchmarks
description: 'Write or tune Testo performance benchmarks with #[Bench]. Use when the user asks for "benchmark", "measure performance", "compare two implementations", "micro-benchmark", or mentions Mean/Median/RStDev metrics in a Testo context.'
---

# Benchmarks in Testo (`#[Bench]`)

`#[Bench]` runs a method many times, collects timing statistics, and reports
**Mean, Median, RStDev**, with outlier rejection. The stability target is **RStDev < 2%** —
above that, the result is noisy and shouldn't be used to draw conclusions. This is your own
bar for reading the RStDev column; the diagnostic engine is more lenient and only emits reports
once variance is pronounced (RStDev around 10% and up), so a clean report is not a promise of < 2%.

Requires the `BenchmarkPlugin` to be enabled for the suite. Fetch
`https://php-testo.github.io/llms.txt` for the current `#[Bench]` parameter list — they evolve.

## Canonical shape

```php
<?php
declare(strict_types=1);

namespace App\Bench;

use Testo\Bench;

final class SerializationBench
{
    #[Bench(
        callables: [
            'json_encode'   => 'json_encode',
            'native_serial' => 'serialize',
            'custom'        => [self::class, 'customEncode'],
        ],
        arguments: [['a' => 1, 'b' => [2, 3, 4], 'c' => 'x']],
        calls: 1000,
        iterations: 5,
    )]
    public static function encode(): void
    {
        // The body is the `current` callable: benchmarked against the listed ones and the baseline
        // the percentages compare against. Put your current implementation here.
    }

    public static function customEncode(array $data): string
    {
        return MyEncoder::encode($data);
    }
}
```

Key parameters:

- `callables` — map of `label => callable` to compare. Use it for **A/B** comparisons.
- `arguments` — array of positional arguments, applied to every callable.
- `calls` — invocations per iteration (the inner loop). Increase until per-iteration time is well above timer resolution.
- `iterations` — number of iterations (the outer loop). Drives the statistics (Mean/Median/RStDev).
- `tolerance` — how much slower the marked method (`current`) may be than the fastest callable before the benchmark **fails**, as a fraction of the fastest filtered mean (default `0.02`, i.e. 2%).

## Pass / fail

A benchmark records one assertion: `current` is the fastest, within `tolerance`. It **passes** when `current`'s filtered mean stays within `fastest * (1 + tolerance)`, and **fails** (a real test failure) when an alternative beats it by more. So `#[Bench]` doubles as a guard that your current implementation has not regressed against the alternatives you compare it with.

When you compare implementations of *equivalent* speed, the winner is decided by measurement noise and the gate would flake. Set `tolerance: \INF` to compare them without gating on which one wins — the benchmark then only measures and always passes.

## Selecting or excluding benches

Benches are slow and noise-sensitive, so they shouldn't run on every `testo` invocation. Filter by type:

```
vendor/bin/testo --type=!bench   # everything except benches
vendor/bin/testo --type=bench    # only benches
```

Filter semantics live in the `testo-run-tests` skill. Putting benches in their own `SuiteConfig` (with `BenchmarkPlugin`, enabled per suite) and running `--suite=Bench` works too.

## Writing a clean benchmark

1. **Isolate the work.** Move setup outside the benched callable — building inputs every call inflates the result.
2. **Warm up.** The first iteration is often slower (cold cache). Trust the median over the mean for that reason.
3. **Stabilise.** Iterate until RStDev < 2%. If you can't get there, the system is noisy (background processes, thermal throttling, GC) — say so honestly rather than ship unstable numbers.
4. **Compare like with like.** Same inputs across all `callables`. Don't bench `json_encode($small)` against `serialize($large)`.
5. **Don't benchmark trivial work.** If a call is sub-microsecond and being looped 10 000 times, you are measuring loop overhead, not the work.

## When *not* to write a benchmark

- "Is this fast enough?" without a baseline — measure two implementations against each other, never in isolation.
- I/O-bound code (DB, HTTP, FS) — variance swamps any signal. Use timing tests with clear thresholds instead.
- Code that allocates large objects — GC will dominate. Either disable GC for the bench or reuse buffers.

## Reading results

- **Mean** — sensitive to outliers; high mean with low median = a few slow iterations.
- **Median** — preferred summary for noisy workloads.
- **RStDev** — *relative* standard deviation as a %. Above 2% means the result isn't reproducible at one significant figure.

When reporting comparison results, **always** include RStDev for each variant. A "30% faster" claim with 8% RStDev is noise.

## Pitfalls

- Don't put assertions inside a benched callable — they inflate the timing and obscure the signal.
- Don't share state between iterations unless the benchmark is explicitly testing amortized cost.
- Don't compare benchmarks across machines, CI runners, or PHP versions without re-running both sides on the same host.
- Don't bench in a method that also uses `#[Test]` or `#[TestInline]` — keep benchmark classes separate.
