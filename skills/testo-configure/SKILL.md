---
name: testo-configure
description: Set up or edit `testo.php` — the Testo application config. Use when the user is bootstrapping a project, adding/removing a suite, scoping a finder, wiring an application-wide plugin (coverage, JUnit), or asking "where do I configure Testo".
---

# Configuring Testo (`testo.php`)

Testo's config is a real PHP file at the project root returning an `ApplicationConfig`. No XML, no JSON.
This means: full IDE completion, refactoring, and conditional logic (e.g. CI-only suites).

Fetch `https://php-testo.github.io/llms.txt` before introducing new classes — the namespaces here are
the most commonly drifted-on detail.

## Minimal `testo.php`

```php
<?php
declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(name: 'Unit', location: ['tests/Unit']),
    ],
);
```

Run it: `vendor/bin/testo`. The single suite `Unit` will be discovered under `tests/Unit`.

## Anatomy

> **Suite is the plugin-scope boundary.** A Test Suite is a named, configured collection of Test Cases (a Test Case = methods of one class or functions of one file). Different suites can carry different plugin sets — that's the whole reason `SuiteConfig::$plugins` and `SuitePlugins::only(...)` exist.

`ApplicationConfig` takes:

- `src` — directories (string[] or `FinderConfig`) holding **production** code. Used by coverage and inline tests.
- `suites` — array of `SuiteConfig`, one per logical test area.
- `plugins` — application-wide plugins (coverage, JUnit, anything cross-cutting).

`SuiteConfig` takes:

- `name` — string, must be unique. Selectable via `--suite=<name>`.
- `location` — directories or `FinderConfig` for the suite's test files.
- `plugins` — suite-specific plugins, or `SuitePlugins::only(...)` to override inherited application plugins.

`FinderConfig(includes, excludes)` — when a flat array isn't enough (e.g. exclude module's own tests):

```php
new FinderConfig(
    includes: ['core', 'plugin', 'bridge'],
    excludes: ['plugin/assert/tests', 'plugin/codecov/tests'],
);
```

## Multi-suite layout

A typical real-world `testo.php`:

```php
<?php
declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Codecov\CodecovPlugin;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Report\CloverReport;
use Testo\Inline\InlineTestPlugin;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(name: 'Unit',        location: ['tests/Unit']),
        new SuiteConfig(name: 'Integration', location: ['tests/Integration']),
        new SuiteConfig(
            name: 'Sources',
            location: ['src'],
            plugins: SuitePlugins::only(new InlineTestPlugin()),
        ),
    ],
    plugins: [
        new CodecovPlugin(
            level: CoverageLevel::Line,
            reports: [
                new CloverReport(__DIR__ . '/runtime/clover.xml', 'MyProject'),
            ],
        ),
    ],
);
```

Notes on the example:

- `Sources` scans the production tree to pick up `#[TestInline]` cases; `SuitePlugins::only` ensures only `InlineTestPlugin` runs for that suite.
- `CodecovPlugin` is application-wide → it applies to every suite.
- Names are arbitrary; keep them short — they appear in CI logs and in `--suite=`.

## Conditional / dynamic config

Because `testo.php` is real PHP, conditional logic is fine:

```php
$ciOnly = \filter_var(\getenv('CI'), FILTER_VALIDATE_BOOLEAN);

return new ApplicationConfig(
    src: ['src'],
    suites: \array_merge(
        [new SuiteConfig(name: 'Unit', location: ['tests/Unit'])],
        $ciOnly ? [] : [new SuiteConfig(name: 'Sandbox', location: ['tests/Sandbox'])],
    ),
);
```

Keep it readable — if the logic gets long, extract a helper, don't pile up ternaries.

## CLI cheat-sheet

```
vendor/bin/testo                       # all suites
vendor/bin/testo --suite=Unit          # one suite
vendor/bin/testo --suite=Unit --suite=Integration  # multiple
vendor/bin/testo --path=tests/Unit/Foo # subdirectory of a suite
vendor/bin/testo --filter='UserService'  # by name
vendor/bin/testo --type=test           # only #[Test], not benches/inline
vendor/bin/testo --coverage            # force coverage on
vendor/bin/testo --no-coverage         # force coverage off
vendor/bin/testo --teamcity            # TeamCity output (CI/IDE)
vendor/bin/testo --config=path/to/testo.php
```

## Pitfalls

- **`src` must include production code only**, not tests. Otherwise inline tests will run against test fixtures and coverage will count test files.
- **`SuitePlugins::only(...)` replaces inherited plugins.** If the suite still needs coverage, add `CodecovPlugin` to the `only()` list too.
- **Don't name two suites the same.** The first one wins silently in some builds — keep names unique.
- **Don't shell out to `composer dump-autoload` from `testo.php`.** Composer's autoloader is already booted by the CLI entry. Avoid side-effects in config.
- **Don't read environment variables without a fallback.** `\getenv('FOO') ?: 'default'` — CI dropouts otherwise silently change which suites run.
- **`testo.php` is required**, not auto-generated. If a project doesn't have one, create the minimal version above as step 1.
