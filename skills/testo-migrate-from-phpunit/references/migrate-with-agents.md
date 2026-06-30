# Approach B — AI-agent migration (no Rector)

Port each test file end-to-end with subagents, using the `phpunit-to-testo-map.md` mapping. Use this
when Rector/the bridge cannot be installed (version conflict, restricted environment), when the suite
is small, or when the tests are too unusual for the mechanical rules to help. It is more flexible than
Rector (an agent understands intent — it can turn a mock into a sensible fake) but slower, costlier,
and the agent must apply the **argument-order flip** by hand on every comparison, so it is more
error-prone than Rector for the bulk rewriting. Prefer Approach A when the bridge is available.

Prerequisite: a **restore point** (skill Phase 1) and an agreed **scope** (skill Phase 2). All
commands run from the project root. `<php>` is the resolved binary; `<skillDir>` is this skill's folder.

## Stage B1 — Build the work-list

Scan the scope and rank the files by how much work each needs:

```bash
<php> <skillDir>/scripts/scan-residuals.php --scope=tests/Unit          # repeat --scope per dir
```

It writes `<outDir>/migration-report.md` (ranked summary — **read this first**) and
`<outDir>/migration-batches/NNN.json` (per-file work-lists; `<outDir>` = `runtime` if it exists, else
`build`). Each entry has the file path, a `tests` count, an ordered `needs[]` to-do list (structural
first), and `hints{}`. Here the to-do list is the **full** port (assertions included), because no
Rector pass ran ahead of it.

If `scan-residuals.php` finds nothing, the files aren't PHPUnit tests under that scope — re-check the path.

## Stage B2 — Configure Testo first

Before porting, stand up `testo.php` so subagents can run their ported file immediately. **Don't
hand-write it — generate it with the built-in command**, which scans `tests/` for known suite folders
(`Unit`, `Integration`, `Functional`, `Acceptance`, `Feature`, `E2E`, `Contract`) and writes one
`SuiteConfig` per detected suite:

```bash
vendor/bin/testo init --no-interaction        # generates testo.php + composer scripts from the existing tree
```

- During migration the test directories already exist, so `init` picks up the real structure (it
  always ensures a `Unit` suite, creating `tests/Unit/` if missing).
- **If `testo.php` already exists**, `init` skips it (warns in `--no-interaction`); adjust the file by
  hand instead — see `testo-configure` for the `SuiteConfig` / finder shape.
- For anything `init` can't infer (a non-standard test dir, `@requires`-style suite separation via
  finder excludes), edit the generated file per `testo-configure`.

Then confirm it loads:

```bash
vendor/bin/testo --json --suite=<name>     # should run (likely 0 tests) without config errors
```

**Gate:** the command exits cleanly (an empty or all-PHPUnit suite is fine at this point). A config
error here blocks every subagent's gate, so fix it now.

## Stage B3 — Port file-by-file with subagents

Hand each file to a porting subagent using the fixed template
`<skillDir>/references/subagent-port-prompt.md` **verbatim**, filling every placeholder from the batch
entry (see the skill's placeholder table). The template enforces: rewrite the file per the mapping →
PASS gate (`vendor/bin/testo --json --path=<file>` green, with real assertions) → report.

**Concurrency — parallel within a batch.** Each subagent rewrites a single, independent test file and
runs only that file (`--path=<file>`), so a batch may be dispatched **in parallel**: issue one
subagent per file in the batch in a single message, then wait for all verdicts before the next batch.

Two things to keep serial / shared, not duplicated across parallel subagents:
- **`testo.php`** — already configured in Stage B2; subagents must not edit it.
- **A shared fake/helper** two files both need — create it once (a quick serial step or a dedicated
  subagent) before dispatching the batch, so parallel subagents don't each invent a divergent copy.

Process **one batch at a time, in order**; read only the current batch.

> Why parallel here but strictly sequential in `testo-mutation-testing` / `testo-increase-coverage`:
> those skills have every subagent mutating or covering the **same** source files and gating on a
> **shared** report, so concurrency corrupts state. Migration subagents each own a **different** test
> file — independent work, safe to fan out.

## Stage B4 — Verify the whole suite

After all batches:

```bash
vendor/bin/testo --json --suite=<name>
```

**Gate:** `status: "passed"`. Common `failures[]` causes:
- a flipped comparison (`Assert::same` args in PHPUnit order) — the classic hand-port error;
- a class still `extends TestCase` or a method still named `testFoo` and so not discovered;
- a provider method picked up as a test (make it `public static` returning `iterable`, never `void`).

Then retire the old harness as in Approach A Stage A6 (delete `phpunit.xml`, drop `phpunit/phpunit`
and `tests/bootstrap.php`) — only after the suite is green under Testo.

If it cannot be made green, fall back to the Phase-1 **restore point** and report to the user rather
than leaving a half-migrated suite.

## Troubleshooting

- **Subagent reports the ported file "passes" but it asserts nothing.** A test that runs code but
  makes no assertion is `Status::Risky` in Testo, not `Passed` — the template forbids this; reject the
  port and re-dispatch.
- **A test depends on a PHPUnit mock with complex expectations.** This is the slow row: have the
  subagent extract an interface and write a hand-rolled fake that records calls. If that balloons,
  flag the file to the user — keeping Mockery/Prophecy as a dependency is a legitimate choice.
- **`assertThat` with a custom constraint.** No Testo equivalent — the subagent decomposes it into
  explicit `Assert::*` calls reproducing the constraint's checks.
- **Data provider rows lost their labels.** Numeric-keyed PHPUnit arrays become `'label' => [...]`
  yields; keep meaningful labels — Testo's failure output uses them.
- **The whole batch fails the same way.** Usually `testo.php` (Stage B2) is misconfigured for that
  directory's suite — fix the config once, not per file.
