---
name: testo-coverage
description: 'Configure code coverage in Testo via CodecovPlugin, choose coverage level (Line/Branch/Path), wire up reports (Clover/Cobertura/PHPUnit XML), and use #[Covers] / #[CoversNothing] on tests. Use when the user asks about "code coverage", "clover", "cobertura", "infection coverage XML", or `#[Covers]`.'
---

# Code coverage with Testo

Coverage is opt-in via the **`CodecovPlugin`** in `testo.php`. The plugin needs:

1. A coverage **level** — `Line`, `Branch`, or `Path` (each adds cost and information).
2. One or more **report writers** — Clover, Cobertura, PHPUnit XML.
3. Xdebug ≥ 3.1 (in coverage mode) **or** PCOV available on the runner. Without one of them, the plugin will skip. The coverage mode can be set via `xdebug.mode=coverage`, the `-d xdebug.mode=coverage` CLI flag, **or** the `XDEBUG_MODE=coverage` env var — Testo resolves the active mode with `xdebug_info('mode')`, so the env override (used by `composer infect` and IDE coverage runners) counts.

Fetch `https://php-testo.github.io/llms.txt` (and `llms-full.txt` if you need plugin wiring detail)
before editing — exact class names and constructor parameters are authoritative there.

## Canonical wiring in `testo.php`

```php
use Testo\Application\Config\ApplicationConfig;
use Testo\Codecov\CodecovPlugin;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Report\CloverReport;
use Testo\Codecov\Report\CoberturaReport;
use Testo\Codecov\Report\PhpUnitXmlReport;

return new ApplicationConfig(
    src: ['src'],
    suites: [/* ... */],
    plugins: [
        new CodecovPlugin(
            level: CoverageLevel::Line,
            reports: [
                new CloverReport(__DIR__ . '/runtime/clover.xml', 'MyProject'),
                new CoberturaReport(__DIR__ . '/runtime/cobertura.xml'),
                new PhpUnitXmlReport(outputDir: __DIR__ . '/runtime/coverage-xml'),
            ],
        ),
    ],
);
```

Then enable on the CLI:

```
vendor/bin/testo --coverage
vendor/bin/testo --no-coverage   # explicit off, overrides config
```

`--coverage` makes coverage **mandatory** (`CoverageMode::Always`): the run aborts with
`CoverageDriverNotAvailable` (non-zero exit) when no Xdebug/PCOV driver is present — even with no
report flags. That makes a bare `vendor/bin/testo --coverage` a handy CI gate to assert the driver
is actually available. `--no-coverage` always wins over everything.

## CLI report flags (no `testo.php` needed)

A `CodecovPlugin` ships in the application defaults in **shadow** (inert) mode, so three flags let
external tools (the IDE plugin, Infection) pin report destinations **without any `testo.php` change**:

```
vendor/bin/testo --coverage-clover=build/clover.xml
vendor/bin/testo --coverage-cobertura=build/cobertura.xml
vendor/bin/testo --coverage-xml=build/coverage-xml      # directory, for Infection
```

- **Soft activation.** Passing any of these implies coverage collection *if a driver is available*;
  with no Xdebug/PCOV the run skips silently (no file). `--no-coverage` still wins and disables it.
- **Parallel with your config.** If `testo.php` already declares a `CodecovPlugin`, the flag-driven
  reports run **alongside** your configured ones — both sets of files are written. The two are
  **merged** into a single coverage collection (no double overhead): the deepest requested level
  wins, test-type filters are unioned, and every report (yours + the CLI ones) is emitted.
- The shadow stays fully inert when no report flag is present, so default behavior is unchanged.

## Choosing the level on the CLI

```
vendor/bin/testo --coverage-level=branch --coverage-clover=build/clover.xml
```

`--coverage-level` takes `line`, `branch` or `path` (case-insensitive) and **pins** the depth for the
whole run — it wins over every `CodecovPlugin(level: …)`, including when it asks for less. An unknown
value aborts the run rather than falling back.

Without the flag the configured levels are merged and the deepest wins, so a
`new CodecovPlugin(level: CoverageLevel::Branch)` in `testo.php` still applies to a run that only the
CLI report flags activated.

## Picking the coverage level

| Level | Cost | When |
|---|---|---|
| `Line` | Low | Default for CI gates. |
| `Branch` | Medium | When you need to be sure `if`/`match`/`?:` branches are exercised. |
| `Path` | High | Mutation testing setup, exhaustive analysis. Usually local-only. |

Don't ship `Path` on every CI run — it's the slowest. Reserve it for mutation testing or scheduled jobs.

**With fibers (`#[RunInFiber]`), use `Line` only.** `Branch` and `Path` enable Xdebug's branch
analysis, which corrupts memory and crashes the process when it runs inside a fiber — no PHP error, no
report. The bug persists in current Xdebug builds (reproduced on 3.5.3). Testo tries to stop such a
test with `BranchCoverageUnsafeInFiber`, but the guard is tied to the Xdebug version and doesn't fire
on every build — don't rely on it. PCOV is unaffected (it only does `Line` anyway).

## `#[Covers]` and `#[CoversNothing]`

Declare which production classes a test exercises. This scopes coverage reports and surfaces dead tests.

```php
use Testo\Codecov\Covers;
use Testo\Codecov\CoversNothing;

#[Test]
#[Covers(UserService::class)]              // class-level — applies to every test in the class
final class UserServiceTest { /* ... */ }

#[Test]
#[Covers(OrderTotal::class)]
#[Covers(TaxCalculator::class)]            // repeatable: multiple covered targets
final class CheckoutTest { /* ... */ }

#[Test]
#[CoversNothing]                            // explicitly exclude from coverage attribution
final class SmokeTest { /* ... */ }
```

Rules (this is project policy in many Testo codebases — confirm before changing):

- **Class-level `#[Covers]`** when every test in the class covers the same production class. This is the default.
- **Method-level `#[Covers]`** when tests in the same class cover different classes.
- Free functions: pass the FQN as a string — `#[Covers('App\\helpers\\format_money')]` (verify against `llms.txt` for the version in use).

## Reports cheat-sheet

| Report | Format id | Typical consumer |
|---|---|---|
| `CloverReport` | `clover` | Codecov, Coveralls, GitHub coverage diffs. |
| `CoberturaReport` | `cobertura` | GitLab/Jenkins coverage UI. |
| `PhpUnitXmlReport` | `coverage-xml` | **Infection** (mutation testing). |

For Infection, point `infection.json`'s `coverage.path` at the directory you gave to `PhpUnitXmlReport`.

Every written report is announced, not printed: the run dispatches
`Testo\Event\Report\ReportFileGenerating` once it knows coverage will be collected and
`ReportFileGenerated` after the file is written, and whichever renderer owns stdout states it — a plain
line in a terminal, a `##teamcity[testoReport …]` service message under `--teamcity`. The format id in
the table is what a consumer switches on.

`CoverageReport` therefore has two methods: `generate()` and `info(): ReportInfo`
(`Testo\Core\Report\ReportInfo` — format, label, and a `Stringable` location). The location is what a
consumer opens: for a report that fills a directory, the index inside it rather than the directory; for
one that uploads its data, whatever URL it lands on.

```php
final readonly class MyReport implements CoverageReport
{
    public function __construct(private string $path) {}

    public function generate(CoverageResult $result): void { /* write $this->path */ }

    public function info(): ReportInfo
    {
        return new ReportInfo('my-format', 'My coverage', Path::create($this->path));
    }
}
```

## Pitfalls

- No coverage written? Check the active Xdebug mode includes `coverage` — set it via `xdebug.mode`, `-d xdebug.mode=coverage`, or `XDEBUG_MODE=coverage` (or load PCOV). Testo skips the driver if neither is available.
- `clover.xml` empty? Suite-level finder probably excludes the `src` directory you expected — verify the `FinderConfig` covers it.
- Don't enable coverage in benchmark suites — it falsifies timings.
- Coverage under `#[RunInFiber]` costs an extra driver stop/start per suspension (the window is closed
  around every fiber switch so each test keeps its own lines). Suspension-heavy tests pay for it — a
  reason to keep coverage runs on `Line` and off the interleaving-heavy suites.
- Don't write `#[Covers(SomeInterface::class)]` — point at concrete classes that own the executable code.
- Don't combine `#[Covers]` and `#[CoversNothing]` on the same class/method — pick one.
