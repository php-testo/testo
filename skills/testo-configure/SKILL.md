---
name: testo-configure
description: Set up or edit `testo.php` — the Testo application config. Use when the user is bootstrapping a project (including running `vendor/bin/testo init`), adding/removing a suite, scoping a finder, wiring an application-wide plugin (coverage, JUnit), or asking "where do I configure Testo" / "how do I initialize Testo".
---

# Configuring Testo (`testo.php`)

Testo's config is a real PHP file at the project root returning an `ApplicationConfig`. No XML, no JSON.
This means: full IDE completion, refactoring, and conditional logic (e.g. CI-only suites).

Fetch `https://php-testo.github.io/llms.txt` before introducing new classes — the namespaces here are
the most commonly drifted-on detail.

## Bootstrap with `init`

If the project has no `testo.php` yet, prefer the built-in command over hand-writing the file:

```
vendor/bin/testo init
vendor/bin/testo init --path=app
vendor/bin/testo init --no-interaction
```

What it does, in order:

1. Ensures the base directory (`--path`, default `.`) exists. **`--path` is treated as the project root** — every subsequent lookup (`src/`, `tests/`, `composer.json`) and every path baked into the generated `testo.php` is resolved relative to it.
2. Resolves the **source directory** *under `--path`*:
   - if `<path>/src` exists, uses it;
   - otherwise in interactive mode prompts for a directory (default `src`, must exist *under `--path`*);
   - otherwise (non-interactive) **fails** — create `<path>/src` first or run interactively.
   The path is written into the config relative to `testo.php`, so a `src` entry resolves back to `<path>/src` at runtime, regardless of where `vendor/bin/testo` is invoked from.
3. Creates `<path>/tests/` and scans it for known suite folders:
   `Unit`, `Integration`, `Functional`, `Acceptance`, `Feature`, `E2E`, `Contract`.
   Whatever exists is picked up; `Unit` is always added (and `<path>/tests/Unit/` created if missing).
4. Writes scripts to the `composer.json` **colocated with `--path`** (so a monorepo sub-app updates its own composer.json, not the parent one). If no `composer.json` is present at that path the step is skipped silently.
   - `composer test` → `vendor/bin/testo`
   - `composer test:unit`, `composer test:integration`, … one per detected suite (`vendor/bin/testo --suite=<Name>`).
   Existing keys are preserved.
5. Generates `<path>/testo.php` from the stub, with `src` and one `SuiteConfig` per detected suite. If the file already exists: prompts to overwrite (interactive) or skips with a warning (non-interactive).

When to use `init` vs. hand-editing:

- **New project / empty repo** → run `init` first, then tune `testo.php`.
- **Adding a suite to an existing project** → create the directory (e.g. `tests/Integration`) and re-run `init` to get the matching composer script, **or** edit `testo.php` directly. `init` will not overwrite an existing config unless confirmed.
- **Monorepo / sub-app layout** (a self-contained app under `app/`, with its own `src/`, tests, and optionally `composer.json`) → `vendor/bin/testo init --path=app`. `--path` is the sub-app's project root: `app/src/` must exist, and the generated `app/testo.php` references `src` and `tests/<Suite>` as paths **relative to itself** — so they resolve correctly regardless of where `vendor/bin/testo` is invoked from.

After `init` completes, re-read `testo.php` and adjust `src`, suite locations, and plugins to match the project (see sections below).

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

## Reports on every run

Output plugins are application-wide. Add them to `plugins:` when a report should be produced by every run
rather than only when a flag asks for it:

```php
use Testo\Output\Html\HtmlPlugin;
use Testo\Output\JUnit\JUnitPlugin;

plugins: [
    // runtime/report/index.html; a path ending in .html inlines everything into that one file instead
    new HtmlPlugin(),
    new JUnitPlugin(__DIR__ . '/build/junit.xml'),
],
```

`HtmlPlugin` takes a second path for the report's own JSON document
(`new HtmlPlugin('runtime/report', 'runtime/report.json')`) — the HTML is a view over that
document, and CI or external tooling can consume it on its own. The HTML report opens over `file://`
with no server and reaches no network.

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
vendor/bin/testo init                          # bootstrap testo.php + composer scripts
vendor/bin/testo init --path=app               # bootstrap inside a subdirectory
vendor/bin/testo --config=path/to/testo.php    # run with a config outside the project root
```

Running and filtering (`--json`, `--suite`, `--filter`, `--path`, `--group`, `--type`, report
outputs) are covered by the `testo-run-tests` skill; coverage flags by the `testo-coverage` skill.

## Pitfalls

- **`src` must include production code only**, not tests. Otherwise inline tests will run against test fixtures and coverage will count test files.
- **`SuitePlugins::only(...)` replaces inherited plugins.** If the suite still needs coverage, add `CodecovPlugin` to the `only()` list too.
- **Don't name two suites the same.** The first one wins silently in some builds — keep names unique.
- **Don't shell out to `composer dump-autoload` from `testo.php`.** Composer's autoloader is already booted by the CLI entry. Avoid side-effects in config.
- **Don't read environment variables without a fallback.** `\getenv('FOO') ?: 'default'` — CI dropouts otherwise silently change which suites run.
- **`testo.php` is required.** If a project doesn't have one, run `vendor/bin/testo init` (see *Bootstrap with `init`*) or, for full control, write the minimal version above by hand.
- **An empty run is not a success.** Zero discovered tests → status **risky**, non-zero exit (details in the `testo-run-tests` skill). On the config side the usual causes are a suite `location` pointing at the wrong directory or an empty suite.
