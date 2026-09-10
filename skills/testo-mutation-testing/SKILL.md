---
name: testo-mutation-testing
description: Run mutation testing on a Testo project with Infection (via testo/bridge-infection) — check or install the Infection toolchain, collect surviving mutants efficiently, and kill them by strengthening tests. Use when the user asks about "mutation testing", "infection", "set up / install infection", "MSI", "mutation score", "escaped mutants", "kill mutants", or wants to measure how good the tests really are.
---

# Mutation testing with Testo + Infection

A read-only **pre-flight** (Phase 0), then five phases: **agree on scope** (blocking — see Phase 1), **set up** the scratch dir, **generate coverage**, **collect** surviving mutants, **kill** them. Run every command from the project root (never `cd` into `vendor/bin`).

Related skills: `testo-coverage` (coverage setup), `testo-write-tests` (assertions). Fetch
`https://php-testo.github.io/llms.txt` before editing tests.

## Phase 0 — Pre-flight (toolchain)

Run the bundled check first. It only reads `composer.json`, `vendor/` and `infection.json`, so it needs no confirmation:

```bash
php <skillDir>/scripts/precheck.php          # add --root=PATH when not at the project root
```

`<skillDir>` is this skill's own directory (the folder holding `SKILL.md`). The check covers Infection, `testo/bridge-infection`, whether Infection's extension-installer actually registered the bridge, an `infection.json` with `source.directories`, and a coverage driver. A config without `"testFramework": "testo"` is only a note: Phase 4 passes `--test-framework=testo` on the CLI. It ends with a verdict:

- **READY** → go to Phase 1.
- **NOT READY** → the listed fixes are the install offer. Carry them into the Phase 1 question; on consent follow `references/setup.md` (install commands, minimal `infection.json`, driver check), re-run the precheck until it prints READY, then continue. Nothing later in this skill runs against missing tooling.

## Phase 1 — Agree on scope (BLOCKING — ask first)

**Right after Phase 0, ask the user how far to take this run, then WAIT for their answer.** Do not resolve the scratch dir, generate coverage, or run anything until they reply. The *only* exception: their request already states the scope (a named part, "the whole project", "just give me the report"). Mutation testing's kill phase is long and writes code — never assume the scope and never skip this question.

If Phase 0 said **NOT READY**, the same message also offers to install and configure the missing pieces (quote the precheck's fix list). Installing and choosing the scope are two decisions; collect both in this one exchange. A user who declines the install ends the run here — say what is missing and stop.

Offer these modes:

- **Whole project** — collect across every segment, then kill across all of them. A full CI-style pass.
- **Specific segment(s)** — the user names the parts (or you list the segments from *Choosing segments* and let them pick); only those are collected and killed.
- **Report first** — run everything up to the report (Phases 2–4), then stop before the kill phase and let the user decide which segments / mutants to kill.

If the user answers but is indifferent about depth, fall back to **Report first**. "I don't care / you decide" is a reply to the question — it is NOT a license to skip asking and start on your own.

Coverage (Phase 3) is always generated once for the whole project regardless of the answer; the scope only narrows Phases 4–5. Carry the chosen scope into those phases.

## Phase 2 — Choose the scratch dir

Resolve `<tmpDir>` once; use it in every command in later phases.

1. Open `infection.json`. If it has a `tmpDir` key, `<tmpDir>` = that value.
2. Otherwise `<tmpDir>` = `runtime` if that directory exists, else `build`.
3. Use this dir name in the following commands. Do not create any dirs manually.

## Phase 3 — Generate coverage

### 1. Run once

Important: generate coverage for the whole project in one run, not per segment. So, don't add any filter parameters.

```bash
php -d xdebug.mode=coverage -d xdebug.max_nesting_level=100000 -d sys_temp_dir=<tmpDir> \
  vendor/bin/testo --json \
    --coverage-xml=<tmpDir>/cov/coverage-xml \
    --log-junit=<tmpDir>/cov/junit.xml
```

### 2. Gate — check it passed

If the command exits non-zero (failing tests), YOU MUST STOP: report the failing tests to the user and do not continue.

## Phase 4 — Collect

Run Infection **one segment at a time**, reusing the Phase-3 coverage. A *segment* is a slice of the source tree you mutate in a single invocation, addressed by a path via `--filter`. Per-segment runs bound memory and time, and give a per-segment MSI + work-list.

### Choosing segments

"Segment" is project-specific — derive it from the layout, do not assume a fixed list:

- Start from `source.directories` in `infection.json`.
- **Split** any entry that holds several independent packages/components into its immediate subdirectories — monorepo `packages/*`, framework `plugins/*`, `src/Module/*` — so each run stays small and its report stays isolated.
- A flat single-package project is **one** segment (e.g. `src`); there a segment run *is* the whole-project run, which is fine.
- **Skip** segments that are slow or irrelevant: benchmarks, recursive / deep-recursion fixtures, generated code.
- Name each segment by a short slug (usually its last path component). The slug is the `<segment>` in the output file names below.
- Honor the scope agreed in Phase 1: if the user picked specific parts, run only those segments here.

### 1. Run Infection per segment (reuse coverage)

```bash
php -d memory_limit=4G -d sys_temp_dir=<tmpDir> vendor/bin/infection --quiet --test-framework=testo --skip-initial-tests \
  --configuration=infection.json \
  --coverage=<tmpDir>/cov \           # <tmpDir>/cov = coverage dir from Phase 3
  --filter=<segmentPath> \                                 # <- the segment's source path, e.g. plugin/payments
  --threads=max --log-verbosity=default \
  --logger-summary-json=<tmpDir>/mut/<segment>.json \      # <- name output files by segment slug
  --logger-gitlab=<tmpDir>/mut/<segment>.gitlab.json \
  --no-progress
```

- One run per segment via `--filter`. Never lump a large/monorepo tree into a single run — it OOMs; go segment by segment.
- Loggers: `--logger-summary-json` + `--logger-gitlab` only. NEVER `--logger-text` / `--log-verbosity=all` — it OOMs. Keep `--log-verbosity=default` (`none` disables file loggers).

### 2. Build the report

Do NOT hand-roll an aggregation script. Run the one bundled with this skill:

```bash
php <skillDir>/scripts/aggregate-report.php <tmpDir>/mut          # add --batch=N to change batch size (default 15)
```

It reads every `<segment>.json` + `<segment>.gitlab.json` and writes two things:

- **`<tmpDir>/mutation-report.md`** — the summary. A table (segments sorted by MSI ascending + a project-total row) of total / killed / escaped / not-covered / errors / timeout / MSI, then an index of the batch files. **Read this first.**
- **`<tmpDir>/mut/batches/<segment>-NNN.json`** — the surviving mutants split into batches of up to N (default 15). These are the Phase-5 work-lists; each entry keeps `check_name`, `location.path`, `location.lines.begin`, `content`.

The script also prints the batch files and counts to stdout. Project-total MSI = `(killed + errors + timeouts) / total`. On a big run, **never load a raw `*.gitlab.json` into context** — work from the batches, which is what Phase 5 consumes.

## Phase 5 — Kill

Act on the scope chosen in Phase 1 — do not add an extra confirmation step of your own:

- **Whole project** or **specific segment(s)** → start killing the in-scope mutants **now**, without pausing to ask again.
- **Report first** → STOP here: present the Phase 4 report and let the user pick which segments / mutants to kill, then proceed only on those.

Work-list = the batch files `<tmpDir>/mut/batches/<segment>-NNN.json` from Phase 4 (indexed in the report). **Process one batch file at a time, in order; read only the current batch** — never the raw `<segment>.gitlab.json`. Each batch entry has `check_name`, `location.path`, `location.lines.begin`, `content` (the mutation diff).

Within the current batch, process it as a **strict sequential loop**, one mutant per iteration:

1. Take the next unprocessed mutant from the batch.
2. Spawn **exactly one** subagent for it.
3. **Wait for that subagent's verdict.**
4. Only then go back to step 1 for the next mutant.

**Never spawn subagents in parallel, and never issue more than one subagent call in a single message.** The mutants look independent, so the usual instinct is to fan them out at once — do NOT. They all mutate the *same source files*; concurrent subagents overwrite each other's edits and write tests against a wrong source state (the corruption that mislabels mutants "killed" or "equivalent"). This requirement overrides any general preference for parallelising independent subagent work. (Parallelism would only be safe with one git worktree per subagent — not the flow used here.)

> **IMPORTANT: one subagent handles exactly ONE mutation.** Never batch several mutants into a single subagent, even when they sit on adjacent lines. Two mutations applied at once corrupt each other — a test that fails under the *combination* gets miscredited to every batched mutant as "killed", while mutants with no covering test fall silent and get mislabelled "equivalent". Apply one mutation, gate it, revert it, and only then spawn the next subagent for the next mutation.

### Spawn each subagent from the fixed template

Spawn **one subagent at a time** (the sequential loop above): a single Agent call, wait for its verdict, then the next. Do NOT put multiple subagent calls in one message.

Do NOT write your own subagent instructions. Hand each subagent the template at `references/subagent-kill-prompt.md` (next to this `SKILL.md`) **verbatim**, with every placeholder filled in. Paraphrasing it is how the add-test / red-gate / green-gate steps get silently dropped and escaped mutants get mislabelled "equivalent".

Fill the placeholders from the work-list entry and your resolved paths:

| Placeholder | Source |
|---|---|
| `{{FILE}}` | entry `location.path` |
| `{{MUTATOR}}` | entry `check_name` |
| `{{LINE}}` | entry `location.lines.begin` |
| `{{DIFF}}` | entry `content` |
| `{{TEST_CMD}}` | `<php> -d sys_temp_dir=<tmpDir> vendor/bin/testo` — just the binary + temp dir; the template appends `--json --no-coverage`, so do NOT bake in any report / coverage / xdebug flags |
| `{{TEST_DIR}}` | the test directory that covers this segment (often a sibling `tests/` of the segment's `src/`) |
| `{{KILL_HINT}}` | the matching `Kill it by` cell from the kill table below |

### What the subagent does (the template enforces this; verify it followed it)

Apply the mutation **surgically — only its own lines** (`content` is not a `git apply`-able patch: bare `@@ @@` header; reconstruct "before"/"after" from the hunk by hand). Then: **already-killed check → add/strengthen a test → RED gate (test MUST fail under the mutation) → revert + verify the lines are restored → GREEN gate (test MUST pass on clean source)**.

**Non-negotiable rules** (keep them in the template, never weaken them):
- **escaped ≠ equivalent.** A surviving mutant is *under-tested*, not equivalent, unless you can prove no input distinguishes it. Default to writing a killing test; mark `equivalent-accepted` only after genuinely failing to construct one.
- A kill is proven only by a test that is RED under the mutation and GREEN without it — never by the existing suite merely passing.
- Surgical apply/revert only; NEVER `git checkout -- <file>` (it destroys WIP, including earlier subagents' tests).
- The subagent does NOT regenerate coverage or run Infection — that is the final pass below.

### After all mutants

Run **one** authoritative pass:
1. Regenerate coverage (Phase 3 step 1) — the new tests change which mutants are covered.
2. Re-run Infection per segment (Phase 4 step 1). Confirm escaped dropped and MSI rose. Cross-kills (one new test killing several mutants) surface here.

Genuinely equivalent mutants (proven to have no observable effect for any input — not merely uncaught by the current suite) are the rare exception: note them as accepted, do not contort tests.

### Kill table

Covers the families in Infection's default profile; the same heuristics generalise to any mutator in that family.

| Mutator family | Change | Kill it by |
|---|---|---|
| `ReturnRemoval` | drops a `return` (method returns null) | Assert the returned value, not just that it ran. |
| `Identical` / `NotIdentical` / `Equal` / `NotEqual` | flips `===` `!==` `==` `!=` | Inputs that distinguish the two (e.g. equal-but-not-identical values). |
| `GreaterThan` / `LessThan` / `…OrEqualTo` (+ their `…Negotiation` negations) | shifts or flips a comparison | Boundary inputs: just below, exactly at, just above. |
| `Plus` / `Minus` / `Multiplication` / `Division`, `IncrementInteger` / `DecrementInteger` | alters arithmetic / `n` → `n±1` | Inputs where the exact numeric result matters; boundaries `n-1, n, n+1`. |
| `TrueValue` / `FalseValue` | flips a literal bool | Exercise both branches; assert the differing outcome. |
| `LogicalAnd` / `LogicalOr` | swaps AND ↔ OR | Inputs where only one operator gives the expected result (one operand true, the other false). |
| `IfNegation` / `LogicalNot` / `LogicalAndNegation` / `LogicalOrNegation` / `…AllSubExprNegation` | negates a condition or operand | Cover both branches; an input whose outcome flips when the condition is negated. |
| `MethodCallRemoval` / `FunctionCallRemoval` | drops a call | Assert the observable side effect of the call. |
| `Concat` / `ConcatOperandRemoval` | reorders or drops a `.` operand | Assert the full composed string; make each operand and its position observable. |
| `ArrayItemRemoval` / `ArrayOneItem` | drops an array item / keeps only one | Assert the full set and count of elements; include a case sensitive to the dropped item. |
| `SpreadRemoval` / `SpreadOneItem` | drops or shrinks a `...` spread | A case where the spread contributes more than one element that affects the result. |
| `PregQuote` / `UnwrapTrim` / `UnwrapLtrim` / other `Unwrap*` | replaces `f(x)` with `x` | Input where `f`'s transformation matters (whitespace to trim, regex metachar to quote, case to fold). |
| `Coalesce` / `AssignCoalesce` | drops `??` / `??=` guard | Test the already-set path (assert no overwrite) and the null-fallback path. |
| `MatchArmRemoval` / `SharedCaseRemoval` | drops a `match` / `case` arm | One case per arm asserting its distinct result. |
| Loop mutators (`Foreach_` / `For_` / `While_` / `Continue_` / `Break_`) | empties a loop body or alters its control flow | Input with ≥2 iterations where the loop's cumulative effect (or the skipped / early-exit element) is asserted. |
| Cast removal (`CastInt` / `CastString` / `CastBool` / …) | drops a cast | Input where the uncast type changes behaviour (`"1"` vs `1`, float truncation). |

Boundary/branch survivors are usually a missing `#[DataSet]` row — add the case, not a new method (see `testo-data-driven`).
