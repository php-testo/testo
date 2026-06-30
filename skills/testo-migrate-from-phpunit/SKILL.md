---
name: testo-migrate-from-phpunit
description: Migrate an existing PHPUnit (or Pest) test suite to Testo — via the Rector bridge, via AI-agent rewriting, or a combination. Use when the user says "migrate from PHPUnit", "port phpunit tests", "convert TestCase to Testo", or has files extending PHPUnit\Framework\TestCase that need to move to Testo's attribute-based style.
---

# Migrating PHPUnit → Testo

Testo is similar in spirit to PHPUnit but **not source-compatible**: assertion argument order flips,
discovery is attribute-based, and there is no base class. This skill orchestrates the migration as
**phases**, then routes to one of two detailed approach references:

- **Approach A — Rector-assisted** (`references/migrate-with-rector.md`): `testo/bridge-rector` does
  the mechanical bulk deterministically; an AI/human pass finishes the structural part. *Recommended
  when the bridge can be installed.*
- **Approach B — AI-agent rewrite** (`references/migrate-with-agents.md`): subagents port each file
  end-to-end using the mapping. For when Rector is unavailable, the suite is small, or tests are
  too unusual for the rules.

The PHPUnit → Testo construct mapping (shared by both) lives in `references/phpunit-to-testo-map.md`.
Run every command from the project root (never `cd` into `vendor/bin`). `<skillDir>` is this skill's
own directory (the folder holding `SKILL.md`). Fetch `https://php-testo.github.io/llms.txt` before
writing tests.

> Pest → Testo: the bridge ships only `expect()->toX()` → `Assert::*`; the `test()`/`it()`
> functional→class restructuring is not automatable. Treat a Pest suite as Approach B (AI rewrite)
> with that one Rector set as a helper. The phases below still apply.

## Phase 1 — Restore point (BLOCKING — do this first)

**Migrating tests is a breaking change to the test suite. Your very first action is to make sure
there is a restore point, then tell the user — and WAIT for their acknowledgement before changing any
test.** A migration can leave the suite red; the user must be able to roll back cleanly.

1. Check the working tree: `git status --short`.
2. If it is **not clean**, tell the user and ask them to commit or stash first — do not migrate on top
   of unrelated uncommitted work (a rollback would also discard theirs).
3. If it is clean, state the rollback plan explicitly, e.g. *"I'll work on a branch `migrate-to-testo`;
   if the migration fails you can `git checkout <previous-branch>` to discard everything."* Prefer a
   dedicated branch (`git checkout -b migrate-to-testo`) or, at minimum, confirm the current commit is
   a safe point to reset to.
4. Say plainly: **the test suite may be red during and after migration; the restore point is how we
   undo it if it doesn't pan out.**

If the project is **not** under version control, this is blocking: tell the user a migration without a
restore point is unsafe and ask them to initialise git (or make a backup copy) before proceeding.

Do not continue to Phase 2 until the user has acknowledged the restore point.

## Phase 2 — Choose the scope

Decide *which* tests migrate in this run. Offer the user these options (and pick a sensible default
from the layout if they're indifferent):

- **Unit tests only** — the safe, common starting point: pure, mock-light tests port cleanly. Good
  first cutover.
- **A named directory / suite** — e.g. `tests/Unit/Domain`, one bounded slice.
- **The whole test suite** — everything at once. Heaviest; only for small suites or a committed cutover.

Migrate one slice at a time and keep the rest on PHPUnit until each slice is green — **don't run both
runners against the same tests in CI**. Carry the chosen scope (as directory paths) into every later
phase and into the `--scope` / `--path` flags of the scripts.

## Phase 3 — Detect tooling & choose the approach

### 1. Resolve the PHP binary

Pin `<php>` to an absolute path so it's stable across the run and across subagents:

```bash
php -r "echo PHP_BINARY;"     # e.g. C:\php\php.exe or /usr/bin/php
```

### 2. Run the pre-flight check

```bash
<php> <skillDir>/scripts/precheck.php --scope=<dir>      # repeat --scope per in-scope dir
```

It reports whether `testo/bridge-rector` (and therefore Rector) is installed — **`testo/bridge-rector`
alone is enough, it pulls Rector in** — and surveys the test surface (files, ~tests, and the
hard-to-convert constructs per directory). **Read its output before choosing.**

### 3. Present the approach options and let the user choose

Based on the pre-flight result, offer:

- **Rector-assisted (Approach A)** — *recommended when the bridge is available.* Fast, deterministic
  bulk conversion + a finishing AI pass. → `references/migrate-with-rector.md`.
- **AI-agent rewrite (Approach B)** — everything ported by subagents. Use when the bridge isn't
  installable, the suite is tiny, or tests are too unusual for the rules. →
  `references/migrate-with-agents.md`.
- **Combine** — Rector-assisted for the mechanical-heavy directories, AI-only for the gnarly ones
  (heavy mocking, custom constraints). Run the two references per slice.

State the trade-off honestly: **Rector ≠ a complete migration.** It never removes `extends TestCase`
or reconciles discovery, so *every* approach ends with an AI/human structural pass and a full-suite
verification. The choice is mainly *how much of the mechanical rewriting Rector does for you* vs. the
AI doing it (where the AI must apply the argument-order flip by hand, more error-prone).

If `precheck.php` says **RECTOR PATH: NOT READY**, default to recommending the bridge install
(one `composer require --dev testo/bridge-rector`) unless the user declines — then use Approach B.

## Phase 4 — Execute the chosen approach

Follow the matching reference verbatim — each has its own stages, gates, and troubleshooting:

| Choice | Reference |
|---|---|
| Rector-assisted | `references/migrate-with-rector.md` |
| AI-agent rewrite | `references/migrate-with-agents.md` |
| Combine | both, applied per directory slice |

Both routes use the same building blocks:

- **Mapping:** `references/phpunit-to-testo-map.md` — the authoritative construct table + pitfalls.
- **Work-list script:** `scripts/scan-residuals.php` — ranks files and writes batched work-lists
  (`<outDir>/migration-report.md` + `<outDir>/migration-batches/NNN.json`).
- **Porting subagent:** `references/subagent-port-prompt.md` — the fixed, verbatim template for
  porting/finishing one file.

### Subagent concurrency (important — differs from sibling skills)

Migration subagents each rewrite a **different, independent test file** and run only that file
(`--path=<file>`), so a batch **may be dispatched in PARALLEL** — one subagent per file in the batch,
in a single message, then wait for all verdicts before the next batch. This is the opposite of
`testo-mutation-testing` / `testo-increase-coverage`, where subagents share source/reports and must
run strictly sequentially. Keep two things out of the parallel subagents' hands: **`testo.php`**
(configured once by you, not per file) and any **shared fake/helper** two files need (create it once,
up front). Process one batch at a time, in order; read only the current batch.

When filling the subagent template, map the batch entry + your resolved paths to its placeholders:

| Placeholder | Source |
|---|---|
| `{{FILE}}` | batch entry `path` |
| `{{NEEDS}}` | batch entry `needs[]`, rendered one item per line |
| `{{HINTS}}` | batch entry `hints{}`, rendered `key: hint` per line |
| `{{MAP}}` | absolute path to `<skillDir>/references/phpunit-to-testo-map.md` |
| `{{TEST_CMD}}` | `<php> vendor/bin/testo` — just the binary; the template adds `--json --path=` itself |
| `{{TEST_DIR}}` | the test directory the file lives under |

## Phase 5 — Verify & retire the old harness

After the chosen approach's stages complete, run the shared final pass (detailed in each reference):

1. **Full in-scope run:** `vendor/bin/testo --json --suite=<name>`. **Gate:** `status: "passed"`.
   Investigate every `failures[]` — the usual culprits are a flipped comparison, a class still
   `extends TestCase` (undiscovered), or a provider method mistaken for a test.
2. **Retire the old harness — only once green:** delete the disposable Rector config (Approach A);
   when the whole project is migrated, remove `phpunit.xml`, `phpunit/phpunit` from `composer.json`,
   and any `tests/bootstrap.php`. Optionally drop `testo/bridge-rector` from `require-dev`.
3. **Report** the before/after to the user: files migrated, tests now green under Testo, anything
   left on PHPUnit, and any files a subagent reported `blocked` (needs a human decision — usually
   heavy mocking or a PHPUnit-only constraint).

If a slice cannot be made green, **fall back to the Phase-1 restore point** and report to the user —
do not leave a half-migrated, red suite in place.

## Related skills

- `testo-configure` — writing `testo.php` (suites, finder, plugins).
- `testo-write-tests` — `#[Test]`, `Assert`, `Expect`, lifecycle detail.
- `testo-data-driven` — choosing `#[DataSet]` / `#[DataProvider]` / `#[DataZip]` / `#[DataCross]`.
- `testo-coverage` — `#[Covers]` policy and coverage wiring.
