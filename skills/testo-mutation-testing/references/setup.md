# Setting up Infection for a Testo project

Reference for Phase 0 of `testo-mutation-testing`, used when `scripts/precheck.php` reports
**NOT READY** and the user agreed to install. Run every command from the project root.

## 1. Install

Infection loads test-framework adapters through its `infection/extension-installer` Composer
plugin. Allow the plugin **before** requiring the packages, otherwise a non-interactive Composer
refuses it and the bridge stays unregistered:

```bash
composer config allow-plugins.infection/extension-installer true
composer require --dev infection/infection testo/bridge-infection
```

- `testo/bridge-infection` does **not** pull Infection in — require both.
- Coverage XML comes from `testo/codecov`, which ships with `testo/testo`; nothing extra to install.
- Projects that isolate tools with `bamarni/composer-bin-plugin` install into a namespace instead:
  `composer bin infection require --dev infection/infection testo/bridge-infection` (and allow the
  plugin inside that namespace's `composer.json`). With bin-links on, the binary still lands in
  `vendor/bin`.

## 2. Configure `infection.json`

Minimal config. `"testFramework": "testo"` is the only Testo-specific key and it is optional for
this skill (every command passes `--test-framework=testo`); set it so bare `vendor/bin/infection`
runs work too. The bridge is auto-discovered.

```json
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": ["src"]
    },
    "testFramework": "testo",
    "tmpDir": "runtime",
    "timeout": 10
}
```

- `source.directories` — the code to mutate. Phase 4 splits these into segments; list real
  package roots, never `tests`.
- `tmpDir` — Phase 2 reads it; pick a git-ignored dir (`runtime` or `build`) and add it to
  `.gitignore` if missing.
- `timeout` — seconds per mutant test run; raise it for slow suites, otherwise slow tests are
  reported as `timeout`.
- Loggers are passed on the CLI in Phase 4, so no `logs` block is needed.

## 3. Coverage driver

Infection needs per-test coverage, produced in Phase 3 with Xdebug (`xdebug.mode=coverage`) or
PCOV. Check the PHP that will run the tests:

```bash
php -m | grep -iE 'xdebug|pcov'
```

No driver → install one (`pecl install xdebug` / `pecl install pcov`, or the distro package) before
Phase 3. Driver rules live in `testo-coverage`.

## 4. Verify

```bash
vendor/bin/infection --version
php <skillDir>/scripts/precheck.php
```

Precheck must print **READY**. If it still says the bridge is not registered, run
`composer install` once more so extension-installer regenerates its config, then re-check.
