---
name: testo-increase-coverage
description: Report and raise line coverage on a Testo project — collect coverage once, aggregate the Clover report into a ranked, LLM-friendly work-list, then optionally write tests for the least-covered files with subagents. Use both for read-only coverage questions and for improving coverage. Triggers: "increase coverage", "improve test coverage", "cover untested code", "find uncovered lines", "raise the coverage percentage", "what isn't tested", "what's our test coverage", "coverage status/report", "how well tested is X", "is X covered".
---

# Increasing coverage with Testo

Four phases: **agree on scope** (read-only goes straight to the report; improving is gated — see Phase 1), **collect coverage**, **build the work-list**, **cover** the gaps with subagents. Run every command from the project root (never `cd` into `vendor/bin`).

Related skills: `testo-coverage` (plugin/report wiring, `#[Covers]`), `testo-write-tests` (assertions, lifecycle), `testo-data-driven` (`#[DataSet]` for branches/boundaries). Fetch `https://php-testo.github.io/llms.txt` before writing tests.

This skill targets **line coverage** (which statements never executed). To kill *under-tested but executed* logic — flipped operators, dropped calls — use `testo-mutation-testing` instead; the two are complementary. Coverage finds code no test reaches; mutation finds code no test *checks*.

## Phase 1 — Agree on scope

First decide whether the request is **read-only** or **improve**:

- **Read-only — just reporting coverage state** ("what's our coverage", "what isn't tested", "how well tested is X", "show me the report"). Do **not** ask anything and do **not** block — this is **Report first** by default: run Phases 2–3 on the whole project (narrow with `--scope` in Phase 3 if the user named a part), present the report, and STOP. Reporting is cheap and writes no code, so there is nothing to gate.
- **Improve — the user wants to raise coverage / write tests** ("increase coverage", "cover this", "fix the gaps"). The cover phase (Phase 4) is long and **writes test code**, so it is gated: **your very first action is to ask the user how far to take this run, then WAIT for their answer.** Do not pick an output dir, collect coverage, or run anything until they reply. The *only* exception: their request already states the scope (a named part, "the whole project"). Never assume the scope and never skip this question.

When in doubt about which mode applies, treat it as read-only (run to the report, then ask whether to start covering) — that never writes code unasked.

For the **improve** path, offer these modes:

- **Whole project** — build the work-list across every file, then cover them worst-first.
- **Specific segment(s)** — the user names the parts (or you list candidates from the report's top rows and let them pick); only those files are covered. Pass them as `--scope=` filters in Phase 3.
- **Report first** — run everything up to the report (Phases 2–3), then stop before the cover phase and let the user decide which files to take on.

If the user answers but is indifferent, fall back to **Report first**. "I don't care / you decide" is a reply to the question — it is NOT a license to skip asking and start on your own.

Coverage (Phase 2) is always collected once for the whole project regardless of the answer; scope only narrows Phases 3–4.

### Resolve the PHP binary

Before running any command — for read-only immediately, for the improve path only after the user has answered the scope question — resolve `<php>`, the PHP CLI binary used for **every** command in this skill. Pin it to an absolute path so it is stable across the run and across subagents, which may launch in a different shell where bare `php` is missing or points elsewhere:

```bash
php -r "echo PHP_BINARY;"     # e.g. C:\php\php.exe or /usr/bin/php
```

`PHP_BINARY` is the exact interpreter currently running — using it guarantees subagents collect coverage with the same PHP (same Xdebug/PCOV). Carry this `<php>` value into every command below, and pass it to each subagent as `{{PHP}}` (Phase 4).

## Phase 2 — Collect coverage

### 1. Pick the output dir

`<outDir>` = `runtime` if that directory exists, else `build`. Use it in every command below. Do not create dirs manually.

### 2. Run once (whole project)

Collect for the whole project in one run — no `--path`/`--filter`, so the report covers everything. Needs an active coverage driver (Xdebug ≥ 3.1 in coverage mode, or PCOV) — see `testo-coverage` for wiring. Emit all three artifacts in the **one** run (they merge into a single collection — no extra overhead):

```bash
<php> -d xdebug.mode=coverage -d xdebug.max_nesting_level=100000 \
  vendor/bin/testo --coverage --json \
    --coverage-clover=<outDir>/clover.xml \
    --coverage-xml=<outDir>/cov-xml \
    --log-junit=<outDir>/junit.xml
```

`--json` collapses stdout to one compact object (`{ "status", "totals", "failures" }`) instead of the full 800-test progress tree — the report files are still written to disk, so it keeps your context clean while losing nothing.

- **`clover.xml`** lists *every* statement with a hit count → the source of the **uncovered line ranges**.
- **`cov-xml/`** (PHPUnit coverage XML) records `<covered by="Class::method"/>` per line → **which tests already cover each file** (it lists only covered lines, so it can't give ranges — that's clover's job).
- **`junit.xml`** maps each test class to its **file** and its **suite** (the test "type", e.g. `Tokenizer/Unit`) → lets the work-list point at the exact existing test file to extend.

Use the default **Line** level — this skill reads statement coverage from the Clover line nodes. (Branch/Path add cost without changing the work-list.)

### 3. Gate — check it passed

`--coverage` makes coverage mandatory: the run aborts non-zero if no driver is available, and a normal non-zero exit means failing tests. Read the verdict from the `--json` object — `status` is `"passed"`/`"failed"`, `failures[]` names the failing tests. **If `status` is not `passed` (or the command exits non-zero), STOP**: report the failing tests / missing driver to the user and do not continue. Don't write tests against a red suite.

## Phase 3 — Build the work-list

Do NOT hand-roll an aggregation script. Run the one bundled with this skill:

This script takes the **three artifacts from Phase 2** — its input flags deliberately mirror the testo CLI flags that produced them, so you pass the same paths back. Always pass `--coverage-xml` and `--log-junit` so each file is annotated with the tests that already cover it:

```bash
<php> <skillDir>/scripts/coverage-report.php <outDir>/clover.xml --coverage-xml=<outDir>/cov-xml --log-junit=<outDir>/junit.xml          # whole project
<php> <skillDir>/scripts/coverage-report.php <outDir>/clover.xml --coverage-xml=<outDir>/cov-xml --log-junit=<outDir>/junit.xml --scope=core/Tokenizer --scope=plugin/assert   # segment(s)
<php> <skillDir>/scripts/coverage-report.php <outDir>/clover.xml --coverage-xml=<outDir>/cov-xml --log-junit=<outDir>/junit.xml --threshold=70 --batch=6                        # under 70%, 6 per batch
```

`<skillDir>` is this skill's own directory (the folder holding `SKILL.md`). These are flags of **this script**, not of `testo` (the testo run in Phase 2 is what wrote the files). Options:

- `--coverage-xml=DIR` / `--log-junit=FILE` — the coverage-xml dir and JUnit log from Phase 2; enrich each file with the test classes already covering it (class, suite/type, test file). Always pass both in Phase 3; the gate in Phase 4 omits them (it only needs ranges).
- `--threshold=N` — keep only files **below N%** line coverage (default `100`: every file with any gap). Lower it to focus on the worst files.
- `--scope=SUBSTR` — keep only files whose path contains `SUBSTR` (repeatable). Use the Phase-1 segment(s) here.
- `--batch=N` — files per batch work-list (default `8`).
- `--root=PATH` — strip this prefix from displayed paths (default: the cwd / project root).

It writes, next to the Clover file:

- **`<outDir>/coverage-report.md`** — the summary. Project total %, then a table of in-scope files **sorted by uncovered statements (most first)** with coverage %, covered/total, and an **Existing tests** column (the test classes + suites already covering each file), then a batch index. **Read this first.**
- **`<outDir>/coverage-batches/NNN.json`** — the work-lists, up to `--batch` files each. Each entry keeps `path`, `pct`, `covered`, `statements`, `missing`, `uncovered_lines` (a compact range string like `"181-182,185,193-194"`), and `covered_by` (the existing tests: `{ test, suite, test_file }` per class). These are the Phase-4 work-lists.

`missing` × file = the impact ranking. Don't load `clover.xml` or `cov-xml/` into context — work from the report and batches.

Read the **Existing tests** column to plan each file:

- **A file already has a covering test class** → the gap is *extending* it: add `#[DataSet]` rows / branch cases to that exact `test_file`, keeping its suite (type) and style. The work-list hands the subagent the file path directly.
- **`_none_`** → no test reaches it at all; the subagent creates a new test in the matching suite's conventions.

Sanity-check the top rows before covering: a `0.0%` file with no covering tests is often **entrypoint / wiring / framework-glue** code exercised only end-to-end (e.g. `Application.php`, a console `Command`, a `Plugin` registrar, a `Renderer`). Those are low-value unit-test targets and may belong to an integration suite or be deliberately uncovered — flag them to the user rather than forcing brittle unit tests. Prioritise files with **branchy domain logic** (parsers, matchers, calculators, formatters), where each uncovered line is a real untested path.

## Phase 4 — Cover

Act on the scope chosen in Phase 1 — do not add an extra confirmation step of your own:

- **Whole project** or **specific segment(s)** → start covering the in-scope files **now**.
- **Report first**, or the **read-only** path from Phase 1 → STOP here: present the Phase 3 report and let the user decide whether (and which files) to cover, then proceed only on those.

Work-list = the batch files `<outDir>/coverage-batches/NNN.json` (indexed in the report). **Process one batch at a time, in order; read only the current batch.**

Within the current batch, process it as a **strict sequential loop**, one *file* per iteration:

1. Take the next unprocessed file entry from the batch.
2. Spawn **exactly one** subagent for it.
3. **Wait for that subagent's verdict.**
4. Only then go back to step 1 for the next file.

**Never spawn subagents in parallel, and never issue more than one subagent call in a single message.** Subagents add tests in a shared test tree (often the same test file for a segment), and a coverage gate run reads a shared Clover file — concurrent subagents clobber each other's edits and each other's gate. This overrides any general preference for parallelising independent work. (Parallelism is only safe with one git worktree per subagent — not the flow used here.)

> **IMPORTANT: one subagent handles exactly ONE file.** Never batch several files into a single subagent. Coverage work needs the agent to read one production file deeply, find its untested paths, and gate the result; folding in a second file dilutes the read and muddies the gate.

### Spawn each subagent from the fixed template

Spawn **one subagent at a time** (the sequential loop above): a single Agent call, wait for its verdict, then the next. Do NOT put multiple subagent calls in one message.

Do NOT write your own subagent instructions. Hand each subagent the template at `references/subagent-cover-prompt.md` (next to this `SKILL.md`) **verbatim**, with every placeholder filled in. Paraphrasing it is how the assert-something rule and the coverage gate get silently dropped — leaving tests that pass but cover nothing.

Fill the placeholders from the work-list entry and your resolved paths:

| Placeholder | Source |
|---|---|
| `{{FILE}}` | entry `path` (the production file to cover) |
| `{{PCT}}` | entry `pct` |
| `{{UNCOVERED}}` | entry `uncovered_lines` (the range string of untested lines) |
| `{{EXISTING_TESTS}}` | entry `covered_by` rendered as a short list — `Fqn\TestClass (suite Tokenizer/Unit) → tests/…Test.php` per item; or `none — no test covers this file yet` when empty |
| `{{PHP}}` | the `<php>` binary resolved in Phase 1 (e.g. `C:\php\php.exe`) — the subagent uses it to run `coverage-report.php` in the gate |
| `{{TEST_CMD}}` | `<php> -d xdebug.mode=coverage vendor/bin/testo` (built from the Phase-1 `<php>`) — the coverage-capable binary; the template adds `--json` to every run (plus `--no-coverage` for the pass run and `--coverage …` for the gate), so do NOT bake in `--json` or any report flag |
| `{{TEST_DIR}}` | the test directory that covers this segment (often a sibling `tests/` of the file's `src`/`core` tree) — fallback when `covered_by` is empty |
| `{{OUT_DIR}}` | the `<outDir>` from Phase 2 (where focused gate Clover files go) |
| `{{SKILL_DIR}}` | this skill's directory (the subagent runs `scripts/coverage-report.php` from it for the gate) |

### What the subagent does (the template enforces this; verify it followed it)

Read the production file's uncovered lines → decide whether to extend an existing test or add one → **write tests that assert real behaviour** for those paths → **PASS gate** (focused tests green with `--no-coverage`) → **COVERAGE gate** (re-collect coverage scoped to the test, confirm the targeted lines are now hit). A test that runs code but asserts nothing is **Risky in Testo, not Passed** — it must make assertions.

**Non-negotiable rules** (keep them in the template, never weaken them):

- **No coverage theatre.** A test that calls a method only to colour the line green is worthless — every test must assert an observable outcome (return value, state change, thrown exception, emitted event).
- A line is "covered" only when the COVERAGE gate shows it executed under the new test — never by the suite merely passing.
- Do NOT edit production code to make it coverable. If uncovered lines are genuinely **unreachable / dead**, the subagent reports `dead-code` for your review — it does not delete or rewrite production code.
- Add `#[Covers(...)]` per project policy (`testo-coverage`): class-level when every test covers the same class, method-level when they differ.
- The subagent does NOT re-collect whole-project coverage — that is the final pass below.

### After all files

Run **one** authoritative pass:
1. Re-collect coverage (Phase 2 step 2) — the new tests change what's covered.
2. Re-run the report (Phase 3). Confirm project % rose and the targeted files climbed. Report the before/after delta to the user.

Genuinely unreachable code the subagents flagged as `dead-code` is the exception: list it for the user as a candidate for removal — do not contort tests to reach it.
