---
name: testo-coverage
description: Configure code coverage in Testo via CodecovPlugin, choose coverage level (Line/Branch/Path), wire up reports (Clover/Cobertura/PHPUnit XML), and use #[Covers] / #[CoversNothing] on tests. Use when the user asks about "code coverage", "clover", "cobertura", "infection coverage XML", or `#[Covers]`.
---

# Code coverage with Testo

Coverage is opt-in via the **`CodecovPlugin`** in `testo.php`. The plugin needs:

1. A coverage **level** — `Line`, `Branch`, or `Path` (each adds cost and information).
2. One or more **report writers** — Clover, Cobertura, PHPUnit XML.
3. Xdebug (in coverage mode) **or** PCOV available on the runner. Without one of them, the plugin will skip.

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

## Picking the coverage level

| Level | Cost | When |
|---|---|---|
| `Line` | Low | Default for CI gates. |
| `Branch` | Medium | When you need to be sure `if`/`match`/`?:` branches are exercised. |
| `Path` | High | Mutation testing setup, exhaustive analysis. Usually local-only. |

Don't ship `Path` on every CI run — it's the slowest. Reserve it for mutation testing or scheduled jobs.

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

| Report | Format | Typical consumer |
|---|---|---|
| `CloverReport` | Clover XML | Codecov, Coveralls, GitHub coverage diffs. |
| `CoberturaReport` | Cobertura XML | GitLab/Jenkins coverage UI. |
| `PhpUnitXmlReport` | PHPUnit-style coverage XML | **Infection** (mutation testing). |

For Infection, point `infection.json`'s `coverage.path` at the directory you gave to `PhpUnitXmlReport`.

## Pitfalls

- No coverage written? Check Xdebug `xdebug.mode=coverage` (or PCOV is loaded). Testo skips the driver if neither is available.
- `clover.xml` empty? Suite-level finder probably excludes the `src` directory you expected — verify the `FinderConfig` covers it.
- Don't enable coverage in benchmark suites — it falsifies timings.
- Don't write `#[Covers(SomeInterface::class)]` — point at concrete classes that own the executable code.
- Don't combine `#[Covers]` and `#[CoversNothing]` on the same class/method — pick one.
