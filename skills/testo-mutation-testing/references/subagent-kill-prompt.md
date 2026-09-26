<!--
  Fixed subagent prompt for Phase 5 of testo-mutation-testing.
  The orchestrator fills every {{PLACEHOLDER}} and hands the rest to the subagent VERBATIM.
  Do NOT paraphrase the procedure or the hard rules — paraphrasing is how the
  add-test / red-gate / green-gate steps get silently dropped.
  Keep in sync with SKILL.md "Phase 5 — Kill".

  Placeholders:
    {{FILE}}      source file to mutate           (gitlab entry: location.path)
    {{MUTATOR}}   mutator name                     (gitlab entry: check_name)
    {{LINE}}      line of the mutation             (gitlab entry: location.lines.begin)
    {{DIFF}}      the unified-diff hunk            (gitlab entry: content)
    {{TEST_CMD}}  php binary + `-d sys_temp_dir=<tmpDir>` + vendor/bin/testo, e.g.
                  "C:\php\php.exe" -d sys_temp_dir=<tmpDir> vendor/bin/testo
                  Do NOT bake in --json / --no-coverage / xdebug coverage / any
                  report flag — the template adds --json --no-coverage itself.
    {{TEST_DIR}}  the test directory covering this segment (often a sibling tests/ of its src/)
    {{KILL_HINT}} the "Kill it by" cell for this mutator family, from the SKILL.md kill table
-->

You are killing **one** escaped mutation-testing mutant in a Testo PHP project. Work on this single mutant only — do not touch any other mutant, file, or unrelated code.

**IMPORTANT: apply exactly ONE mutation — the one below — at any time.** Never have a second mutation applied simultaneously. Two mutations at once corrupt each other and make test results meaningless (a failure under the combination is not proof that *this* mutant is killed). The source must hold this single mutation during the gates and be fully reverted before you finish.

## Target

- File: `{{FILE}}`
- Mutator: `{{MUTATOR}}` at line {{LINE}}
- Mutation (unified-diff hunk — ` ` = context, `-` = original, `+` = mutated):

```diff
{{DIFF}}
```

`content` is NOT an applicable patch (its header is a bare `@@ @@`). Reconstruct by hand: the **"before"** block = context + `-` lines in order; the **"after"** block = context + `+` lines. Apply and revert with surgical edits to those lines only.

## Running tests

**Always run with `--json --no-coverage`.** `--json` prints one compact JSON object (run summary + failed tests only) to stdout instead of the verbose colored tree — it keeps your context clean and tells you exactly what failed.

Focused (your new / changed test):

```
{{TEST_CMD}} --json --no-coverage --path="<testFile>" --filter="<testName>"
```

Whole segment test suite (the already-killed check):

```
{{TEST_CMD}} --json --no-coverage --path="{{TEST_DIR}}"
```

Read the verdict straight from the JSON:

```json
{ "status": "passed", "totals": { "total": 1, "assertions": 2, "passed": 1 }, "failures": [] }
```

`status` is `"passed"` or `"failed"`; `failures[]` names the failing tests. (The process exit code is non-zero on failure too.)

- NEVER add `--log-junit`, `--log-json`, `--coverage-xml`, or any `--coverage-*` flag here — those write report files and would clobber the Phase-3 coverage/junit outputs. `--json` writes only to stdout, so it is safe.

## Kill hint for this mutator family

{{KILL_HINT}}

## Procedure — follow in order, never skip a step

1. **RECORD** the exact "before" block at `{{FILE}}:{{LINE}}`. Confirm it matches the file as it is now (if it doesn't, a previous mutant was left applied — STOP and report `dirty-source`).
2. **APPLY** the mutation: edit those lines to the "after" block. Touch nothing else.
3. **ALREADY KILLED?** Run the whole segment test suite. If a test already FAILS, an earlier mutant's test already covers this one → go to step 6 (revert), report `killed-existing`.
4. **ADD or STRENGTHEN a test** (mutation still applied). Prefer adding a `#[DataSet]` row or an assertion to the **existing** test that covers this line; create a new test method/file only if none fits. Follow the project's test conventions (read the `testo-write-tests` skill first).
5. **RED GATE** (mutation still applied): run your focused test. It **MUST FAIL**. If it passes, your test does not distinguish the mutant — fix it and repeat step 5. (See the equivalence rule below before giving up.)
6. **REVERT**: edit the "after" lines back to the "before" block from step 1. Re-read those lines and confirm they **equal the original**. If not, STOP and report `revert-failed` — do not leave the source mutated.
7. **GREEN GATE** (clean source): run your focused test. It **MUST PASS**.

## Hard rules — do not violate

- **escaped ≠ equivalent.** A mutant is NEVER equivalent just because the existing suite is silent — that only means it is *under-tested*. "Equivalent" means **no input can make observable behaviour differ**. You may mark `equivalent-accepted` ONLY after reasoning about the code and genuinely failing to construct any distinguishing test. State why. Do not contort a test to force a pass.
- Always reach a verdict via the gates: a kill is proven by a test that is RED under the mutation and GREEN without it.
- Do NOT regenerate coverage or run Infection — that is the orchestrator's final pass.
- Do NOT use `git checkout -- <file>` or any file/tree-wide revert; the working tree holds WIP (tests from earlier mutants).

## Report back — exactly this shape

```
mutant:  {{MUTATOR}} {{FILE}}:{{LINE}}
verdict: killed-new | killed-existing | equivalent-accepted | revert-failed | dirty-source
test:    <file::method added or strengthened, or "none">
note:    <one line: the distinguishing input you used, or why it is equivalent>
```
