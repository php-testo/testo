# How to add a new plugin to Testo

This guide walks through everything required to ship a new plugin as a standalone Composer package, end-to-end: from creating the folder in the monorepo to publishing the package on Packagist.

> **Reference implementation:** `plugin/repeat/`. When in doubt, mirror what's there.

## Concepts in 30 seconds

- The monorepo `php-testo/testo` hosts all packages.
- Each plugin lives under `plugin/<short-name>/` and is registered in the root `composer.json` as a path repository.
- Release-please tracks the plugin's version independently in `resources/version.json`.
- A split-publish workflow mirrors `plugin/<short-name>/` into a dedicated `php-testo/<short-name>` repository on every release tag, and Packagist serves `testo/<short-name>` from there.
- The standalone repository is **read-only**; all development happens in the monorepo.

## Pre-requisites

- Push access to the `php-testo` GitHub organization.
- `RELEASE_TOKEN` (in monorepo secrets) has `Contents: Read and write` on the future target repository — top up the token's scope if it is a fine-grained PAT pinned to specific repos.
- `composer` 2+ available locally.

## In the monorepo

### 1. Create the plugin folder

```
plugin/<short-name>/
├── composer.json
├── README.md
├── <ShortName>.php          # optional — only if the plugin exposes a top-level class
├── src/                     # PSR-4 internals
│   └── ...
└── tests/
    └── ...
```

If extracting from core (the most common case), use `git mv` to keep history:

```bash
git mv core/<Module>           plugin/<short-name>/src
git mv core/<Module>.php       plugin/<short-name>/<ShortName>.php   # if present
git mv tests/<Module>          plugin/<short-name>/tests
```

After moving the source, recreate the `Internal/` (or any sub-namespace) layer if it existed, so PSR-4 mapping inside the plugin stays intact:

```bash
mkdir plugin/<short-name>/src/Internal
git mv plugin/<short-name>/src/<MovedFile>.php plugin/<short-name>/src/Internal/<MovedFile>.php
```

### 2. composer.json template

```json
{
    "name": "testo/<short-name>",
    "description": "<one-line description> for the Testo testing framework.",
    "license": "BSD-3-Clause",
    "type": "library",
    "keywords": ["testo", "<short-name>"],
    "authors": [
        {
            "name": "Aleksei Gagarin (roxblnfk)",
            "homepage": "https://github.com/roxblnfk"
        }
    ],
    "funding": [
        {
            "type": "boosty",
            "url": "https://boosty.to/roxblnfk"
        }
    ],
    "require": {
        "php": ">=8.2",
        "testo/testo": "*"
    },
    "autoload": {
        "psr-4": { "Testo\\<UpperShortName>\\": "src/" },
        "files": ["<ShortName>.php"]
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "extra": {
        "branch-alias": {
            "dev-1.x": "1.x-dev"
        }
    }
}
```

Notes:

- `Testo\\<UpperShortName>\\` is the unique namespace for the plugin's internals (e.g. `Testo\\Repeat\\` for `plugin/repeat`).
- The `files` entry eagerly loads the plugin's top-level class. **Drop the `files` key entirely if the plugin has no top-level class** — do not put a PSR-4 root on `Testo\\` (that breaks autoload performance for everyone).
- The single allowed file in `Testo\` from a plugin is `<ShortName>.php` matching the package short name (`Repeat.php` → `\Testo\Repeat`). No exceptions.

### 3. README

Copy the layout of `plugin/repeat/README.md`: logo header, sponsorship/documentation badges, the read-only mirror banner, an `About` section without API specifics, and an `Install` section with Packagist badges. Replace `repeat`-specific bits with your plugin's short name.

### 4. Wire the plugin into the root composer.json

In root `composer.json`:

- **`require`** (alphabetically):
  ```json
  "testo/<short-name>": "^1.0@dev"
  ```
  > `composer validate --strict` rejects unbound (`@dev`) and exact-version (`1.x-dev` as a require constraint) values. Use `^1.0@dev` everywhere for consistency, and keep the plugin's `branch-alias: dev-1.x → 1.x-dev` so the caret matches the path-repo dev version. After the first stable release the constraint stays the same — just drop the `@dev` flag (`^1.0`).
- **`repositories`**:
  ```json
  {
      "type": "path",
      "url": "plugin/<short-name>",
      "options": { "symlink": true }
  }
  ```
- **`autoload-dev.psr-4`** — map test namespace if the plugin has tests under `Tests\<UpperShortName>\`:
  ```json
  "Tests\\<UpperShortName>\\": "plugin/<short-name>/tests/"
  ```

Then install:

```bash
composer require "testo/<short-name>:^1.0@dev"
```

The plugin should land in `vendor/testo/<short-name>` as a symlink/junction.

> If `composer require` reports `(exact version match)` and reverts the lockfile, fall back to editing `composer.json` directly (already done above) and run:
> ```bash
> composer update testo/<short-name>
> ```
> This commonly happens for the very first `require` of a brand-new path-repo package when Composer's resolver hasn't seen the alias yet.

### 5. Wire the plugin's test suite

In root `testo.php`, replace any reference to the old `tests/<Module>/suites.php` with the new path:

```php
require 'plugin/<short-name>/tests/suites.php',
```

#### Suite naming

Use `<PluginName>/<Layer>` style — no colons, no spaces. Layers in use:

- `<Plugin>/Unit` — unit tests for the plugin's own internals
- `<Plugin>/Self` — self-tests that exercise the plugin against Testo itself
- `<Plugin>/Inline` — inline tests collected from the plugin's `src/` (see below)

Example: `Codecov/Unit`, `Lifecycle/Self`, `Bench/Inline`.

#### Plugin with inline tests in `src/`

If the plugin's own source files contain `#[TestInline]`/`#[Bench]` cases (e.g. inline assertions on internal helpers), the plugin must add its own `<Plugin>/Inline` suite — **don't** extend the root SRC suite's `include` paths to reach into the plugin. The root `testo.php` SRC suite must stay plugin-agnostic.

```php
// plugin/<short-name>/tests/suites.php
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Inline\InlineTestPlugin;

return [
    new SuiteConfig(
        name: '<Plugin>/Unit',
        location: new FinderConfig(include: [__DIR__ . '/Unit']),
    ),
    new SuiteConfig(
        name: '<Plugin>/Inline',
        location: new FinderConfig(include: [__DIR__ . '/../src']),
        plugins: SuitePlugins::only(new InlineTestPlugin()),
    ),
];
```

If the plugin **also** has benchmark-style inline cases, additionally include `BenchmarkPlugin` in the `SuitePlugins::only(...)` call.

### 6. release-please config

In `.github/.release-please-config.json`, add the plugin under `packages`:

```json
"plugin/<short-name>": {
    "package-name": "testo/<short-name>",
    "component": "<short-name>",
    "include-component-in-tag": true,
    "changelog-path": "CHANGELOG.md"
}
```

In `resources/version.json`, add the initial version:

```json
"plugin/<short-name>": "0.1.0"
```

### 7. split-publish workflow

`.github/workflows/split-publish.yml` is a single bash-resolving job — component, directory and version are derived from the tag name at runtime. Adding a new plugin needs **one one-line addition**:

```yaml
on:
  push:
    tags:
      - '<short-name>-[0-9]*'   # add this line
```

That's it. The job uses `${tag%-*}` to extract the component (`repeat-0.1.0` → `repeat`, `bridge-infection-0.1.0` → `bridge-infection`) and routes `bridge-*` components to `bridge/<name>`, everything else to `plugin/<name>`.

### 8. Verify locally

```bash
TESTO_CI=1 vendor/bin/testo run
```

The suite count should grow by the number of tests the plugin adds. All previously green tests should still pass.

If the plugin is on a coverage-aware path, also smoke `composer infect` (uses Infection through the `bridge/infection` adapter — verifies the plugin doesn't break mutation testing).

## On GitHub

### 1. Create the target repository

The mirror is read-only — disable Issues and Wiki up front so contributors find them in the monorepo:

```bash
gh repo create php-testo/<short-name> --public \
    --description "<one-line description> (split-published from php-testo/testo)" \
    --homepage "https://github.com/php-testo/testo" \
    --disable-issues \
    --disable-wiki
```

### 2. Default branch (after the first split)

The repository starts empty. After the first split-publish run completes, set the default branch to match the release branch:

```bash
gh repo edit php-testo/<short-name> --default-branch 1.x
```

This matters for Packagist — it reads `composer.json` from the default branch.

### 3. Register on Packagist

Once the first split has populated the repository:

1. Visit https://packagist.org/packages/submit
2. URL: `https://github.com/php-testo/<short-name>`
3. After registration, enable auto-updates by installing the **Packagist** GitHub App on the target repo (or by pasting the Packagist webhook URL into the repo's webhook settings).

## First release flow

1. Commit your changes with a conventional-commit subject scoped to the plugin:
   ```
   feat(<short-name>): bootstrap plugin
   ```
   Touching only `plugin/<short-name>/**` — release-please attributes the commit to the right package by **paths in the diff**, not by scope text. The scope is for changelog readability.
2. Push to the release branch (`1.x`). Release-please opens a PR titled `chore(<short-name>): release 0.1.0`.
3. Merge the PR. Release-please creates a GitHub release with tag `<short-name>-0.1.0` (no `v` prefix — the project convention is `include-v-in-tag: false`).
4. The tag triggers `split-publish.yml`, which pushes `plugin/<short-name>/` into `php-testo/<short-name>:1.x` with a bare tag `0.1.0`.
5. Set the default branch on the target repo (step 2 of "On GitHub" above) and register the package on Packagist.
6. Verify the install path:
   ```bash
   composer require testo/<short-name>:^0.1
   ```

## Common gotchas

- **`Testo\\` in PSR-4** — never. Each plugin gets its own `Testo\\<UpperShortName>\\` PSR-4 root, plus an optional `files` entry for the top-level class. Registering `Testo\\` from a plugin makes Composer's autoloader iterate every plugin on every class lookup.
- **Top-level class name must equal the plugin short name.** `Repeat` from `testo/repeat`, `Assert` from `testo/assert`. No arbitrary classes in `Testo\` from a plugin.
- **`autoload-dev` from a path-repo dependency is ignored** — Composer only loads `autoload-dev` of the root project. That's why test namespaces are mapped in the root `composer.json`.
- **Tag pattern in split-publish** — keep the `[0-9]*` suffix to avoid false triggers from non-release tags like `repeat-experimental`.
- **Branch alias version** — keep `extra.branch-alias.dev-1.x` aligned with the major the `^1.0@dev` constraint expects (`1.x-dev` for the `1.x` line). Bump only on major-line transitions (e.g. `2.x-dev` once a `2.x` branch is born), not on every minor.

## Reference files

| File | Role |
|---|---|
| `task.md` | Overall monorepo restructuring plan |
| `plugin/repeat/composer.json` | Working example of a plugin manifest |
| `plugin/repeat/README.md` | Working example of a plugin README |
| `.github/.release-please-config.json` | Release config — append new packages here |
| `resources/version.json` | Manifest — append starting version here |
| `.github/workflows/split-publish.yml` | Append a new job per plugin |
