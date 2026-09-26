# Approach A — Rector-assisted migration

Use Rector (via `testo/bridge-rector`) to do the **mechanical** bulk of the conversion across the
whole scope in one deterministic pass, then finish with an AI/human **structural** pass. This is the
recommended approach for any non-trivial suite: Rector rewrites hundreds of assert calls (with the
correct argument-order swap), lifecycle methods, data providers and groups in seconds and never makes
a typo. With a mock set added (Stage A2) it also converts the common `createMock`/`createStub` chains onto Double or Mockery. Forms with no faithful target (`prophesize`, `getMockForAbstractClass`, `addMethods`, `withConsecutive`, a variable invocation matcher) stay for a finishing pass, so one is mandatory.

Prerequisite: you have a **restore point** (skill Phase 1) and an agreed **scope** (skill Phase 2).
All commands run from the project root. `<php>` is the binary resolved in the skill (`php -r "echo PHP_BINARY;"`).
`<skillDir>` is the skill directory (the folder holding `SKILL.md`).

## Stage A1 — Install the bridge

If `precheck.php` reported **RECTOR PATH: NOT READY**, install it (the bridge pulls in `rector/rector`):

```bash
composer require --dev testo/bridge-rector
```

**Gate:** re-run `precheck.php`; it must now report **RECTOR PATH: AVAILABLE** and list the
`phpunit-to-testo` set. If Composer refuses (version conflict on `rector/rector`), see Troubleshooting.

## Stage A2 — Generate the Rector config

Scaffold a disposable config scoped to the migration paths (do **not** clobber an existing `rector.php`
— write a separate file):

```bash
<php> <skillDir>/scripts/scaffold-rector-config.php rector-testo-migration.php \
  --path=tests/Unit            # repeat --path for each in-scope dir
```

- `--set=phpunit-to-testo` is the default. `--set` repeats: when the scope uses PHPUnit mocks, list the
  mock set for the library the user picked — `--set=phpunit-to-testo --set=phpunit-to-double` (Double,
  PHP 8.3+) or `--set=phpunit-to-mockery` (Mockery, PHP 8.2). Without one, mocks stay PHPUnit.
  Double raises the test suite's minimum PHP to 8.3: if the project supports older versions, ask the
  user before choosing it, with the same options as the PHP check in SKILL.md Phase 3, plus Mockery.
- Write the file at the **project root** (it uses `__DIR__`-relative paths).
- The script verifies the set exists under `vendor/` before writing and prints the next commands.
- The conversion writes Testo, Mockery and Double names fully qualified (`\Testo\Assert::same()`); the
  polish pass in Stage A6 imports them and drops `use PHPUnit\…` lines nothing refers to any more.
  A `withImportNames()` added to the config is harmless but imports every other name as well.

**Gate:** `<php> -l rector-testo-migration.php` → "No syntax errors".

## Stage A3 — Dry-run, then apply

Always preview first — Rector's `--dry-run` prints a diff and changes nothing:

```bash
vendor/bin/rector process --config=rector-testo-migration.php --dry-run
```

Read the diff. Sanity-check a comparison assertion: `assertSame($expected, $actual)` must become
`Assert::same($actual, $expected)` — **arguments swapped**. If the swap looks wrong, STOP and report;
do not apply.

Apply:

```bash
vendor/bin/rector process --config=rector-testo-migration.php
```

**Gate:** `git diff --stat` shows the expected files changed. Commit this as a checkpoint
(`git commit -am "migrate: mechanical Rector pass"`) so the structural pass has a clean base to diff against.

> Rector detaches each class from `TestCase` and marks its test methods with `#[\Testo\Test]`, including
> subclasses of a project base class, and drops `parent::setUp();`-style statements that call into
> PHPUnit. A class on a PHPUnit base from `vendor/` (a framework's `TestCase`) is converted as well, but
> the class extending that base gets `#[\Testo\Skip]` naming it, since the base still runs on PHPUnit.
> Tests inherited from such a base are not converted. `scan-residuals.php` and the pitfalls in the
> mapping cover them.

## Stage A4 — Plan the structural residue

Scan the (now mechanically-converted) scope for everything left to do:

```bash
<php> <skillDir>/scripts/scan-residuals.php --scope=tests/Unit          # repeat --scope per dir
```

This writes `<outDir>/migration-report.md` (ranked summary — **read this first**) and
`<outDir>/migration-batches/NNN.json` (per-file work-lists). `<outDir>` is `runtime` if it exists,
else `build`. Each file entry carries an ordered `needs[]` to-do list (structural first) and `hints{}`.

Rector already detaches `TestCase` and adds `#[\Testo\Test]`, so the residue is mostly PHPUnit
attributes with no Testo counterpart (`phpunit_attribute`), tests inherited from a PHPUnit base
outside the scope (`external_testcase_parent`, checked through the project autoloader; `--no-autoload`
skips it), and mocks/constraints on a few files. Test methods keep their `test*` names, which is not
residue. Files that only carry an unused `use PHPUnit\…` import are listed under "Cleanup only" and
are not batched. If `scan-residuals.php` reports *no* files needing work, the
suite was unusually simple — skip the subagent dispatch (Stage A5 step 2), but still configure
`testo.php` (Stage A5 step 1) and verify (Stage A6).

## Stage A5 — Finish with subagents (one file each)

### 1. Configure `testo.php` first (before any subagent)

The subagents below gate each file with `vendor/bin/testo --path=<file>`, which needs a working
`testo.php`. **Don't hand-write it — generate it with the built-in command**, which scans `tests/`
for known suite folders (`Unit`, `Integration`, `Functional`, `Acceptance`, `Feature`, `E2E`,
`Contract`) and writes one `SuiteConfig` per detected suite:

```bash
vendor/bin/testo init --no-interaction        # generates testo.php + composer scripts from the existing tree
vendor/bin/testo --json --suite=<name>         # confirm it loads (0 tests is fine at this point)
```

- `init` does not read `phpunit.xml`: bring the generated file in line with the carry-over list
  from `precheck.php` (one suite per `<testsuite>`, the `<php>` settings at the top), and drop suites
  it created for layouts the project does not have.
- The test directories already exist, so `init` picks up the real structure (it always ensures a
  `Unit` suite). **If `testo.php` already exists**, `init` skips it — adjust by hand per
  `testo-configure`. For non-standard dirs or `@requires`-style suite separation (finder excludes),
  edit the generated file per `testo-configure`.
- **Gate:** the command exits cleanly. A config error here blocks every subagent's gate — fix it now.

### 2. Dispatch the porting subagents

Hand each file to a porting subagent using the fixed template
`<skillDir>/references/subagent-port-prompt.md` **verbatim**, filling every placeholder from the
batch entry (see the skill's placeholder table). The template enforces: apply the `needs[]` items →
PASS gate (the file's tests green under `vendor/bin/testo --json`) → report.

**Concurrency:** files are independent (each subagent edits its own test file), so a batch **may be
run in parallel** — issue up to one subagent per file in the batch in a single message. `testo.php`
was configured in step 1 above and the subagents must **not** touch it, so there is no write conflict.
(If a file's port requires a new fake class that a sibling file also needs, note it and create the
shared fake once — that is the only case to serialize.)

Process **one batch file at a time, in order**; within a batch, parallel is fine. Read only the
current batch.

## Stage A6 — Verify the whole suite & retire the old harness

1. Run the full in-scope suite (`testo.php` was set up in Stage A5 step 1):
   ```bash
   vendor/bin/testo --json --suite=<name>
   ```
2. **Gate:** `status: "passed"`, and `compare-junit.php` against the PHPUnit baseline reports
   `IDENTICAL` (SKILL.md, Phase 5 step 2). Investigate any `failures[]` (a flipped assertion that
   slipped through) and any missing tests (an undiscovered class drops out without failing).
3. **Polish pass.** Scaffold a second config with the `testo-polish` set and apply it the same way
   (dry-run first):
   ```bash
   <php> <skillDir>/scripts/scaffold-rector-config.php rector-testo-polish.php --path=tests/Unit --set=testo-polish
   vendor/bin/rector process --config=rector-testo-polish.php
   vendor/bin/rector process --config=rector-testo-polish.php   # again: cleans up traits
   ```
   It adds `: void`/`: never` and `final`, moves `#[Test]` onto the class and one-statement exception
   tests into `#[ExpectException]`, merges assertion pipes, and imports the fully qualified Testo,
   Mockery and Double names, without changing which tests run. Rector appends new `use` lines
   unsorted; run the project's code-style fixer after applying.
   **Gate:** the step-2 checks again give `status: "passed"` and `IDENTICAL`.
4. Remove the scaffolding once green: delete `rector-testo-migration.php` and `rector-testo-polish.php`; if the whole project is
   migrated, drop `phpunit.xml`, `phpunit/phpunit` and `tests/bootstrap.php`, and optionally remove
   `testo/bridge-rector` from `require-dev`. A class skipped for a PHPUnit base from vendor blocks
   this: rewrite the base for Testo and remove the `#[Skip]` first, or the base no longer loads once
   PHPUnit is gone and discovery fails.

Deprecated Testo API (for example `withMessagePattern()`, now `withMessageMatchingRegex()`) is migrated by the `testo-shift` set: scaffold it with `--set=testo-shift` and run it whenever the project upgrades Testo, not only after a migration.

If the migration cannot be made green, this is where the **restore point** from Phase 1 matters —
offer the user a rollback rather than leaving a half-migrated suite.

## Troubleshooting

- **`composer require` fails on `rector/rector` conflict.** The project pins an incompatible Rector.
  Run the AI-only path (Approach B) instead, or upgrade Rector in a branch first.
- **Rector reports "no files were changed".** The `--path` did not point at the test sources, or the
  files don't match the rules (already converted, or not PHPUnit). Verify the path; check `precheck.php`'s
  test-surface table.
- **A comparison assertion came out with the wrong argument order.** That is a rule bug — STOP, keep
  the dry-run diff, and report it (the bridge covers arg-order with fixtures, so this should not happen).
- **`Class "PHPUnit\Framework\TestCase" not found` while running Rector.** Keep `phpunit/phpunit`
  installed *during* migration; remove it only in Stage A6 after the suite is green under Testo.
- **Tests vanish from the Testo run after Stage A3.** A test method without `#[\Testo\Test]`: usually
  one inherited from a `vendor/` PHPUnit base. Port it per the mapping's pitfalls. The conversion set
  runs Rector serially; a `->withParallel()` in the config races on shared base classes and drops
  the attribute from their subclasses, so remove it.
- **A file mixes converted and unconverted assertions after apply.** Rector hit an edge case on that
  file; `scan-residuals.php` flags it as `leftover_assert` and the subagent finishes it by hand.
