<!--
  Fixed subagent prompt for Phase 4 of testo-increase-coverage.
  The orchestrator fills every {{PLACEHOLDER}} and hands the rest to the subagent VERBATIM.
  Do NOT paraphrase the procedure or the hard rules — paraphrasing is how the
  assert-something rule and the coverage gate get silently dropped, leaving tests
  that pass but cover nothing.
  Keep in sync with SKILL.md "Phase 4 — Cover".

  Placeholders:
    {{FILE}}      production file to cover            (work-list entry: path)
    {{PCT}}       current line coverage of that file  (work-list entry: pct)
    {{UNCOVERED}} uncovered line ranges               (work-list entry: uncovered_lines)
    {{EXISTING_TESTS}} tests already covering the file (work-list entry: covered_by), rendered as
                  "Fqn\TestClass (suite Tokenizer/Unit) -> tests/...Test.php" per item,
                  or "none — no test covers this file yet"
    {{PHP}}       the PHP CLI binary resolved in Phase 1 (PHP_BINARY), e.g. "C:\php\php.exe" —
                  used to run coverage-report.php in the gate
    {{TEST_CMD}}  coverage-capable php + vendor/bin/testo, built from {{PHP}}, e.g.
                  "C:\php\php.exe" -d xdebug.mode=coverage vendor/bin/testo
                  Do NOT bake in --json / --no-coverage / report flags — the template
                  adds them itself per step.
    {{TEST_DIR}}  the test directory covering this segment (often a sibling tests/ of the src tree)
    {{OUT_DIR}}   output dir for the focused gate Clover file (the run's runtime/ or build/)
    {{SKILL_DIR}} this skill's directory — holds scripts/coverage-report.php for the gate
-->

You are raising line coverage on **one** production file in a Testo PHP project. Work on this single file only — do not touch any other file's coverage or unrelated code.

## Target

- File: `{{FILE}}` — currently **{{PCT}}%** line coverage.
- Uncovered lines (no test executes these): `{{UNCOVERED}}`
- Tests already covering this file:

{{EXISTING_TESTS}}

These line numbers are statements that never ran during the suite. Your job: write tests that make the meaningful ones execute **and assert their behaviour**.

**Extend before you create.** If a test class above already covers this file, the missing paths almost always belong in *that* file and *that* suite (test type) — add `#[DataSet]` rows or new methods there, matching its style. Create a brand-new test file only when the list says `none`, or when the uncovered behaviour clearly belongs to a different suite/type than the existing tests.

## Before you write anything

1. **Read `{{FILE}}`**, focusing on the uncovered ranges. Group them into untested *behaviours*: an error/guard path, a branch of an `if`/`match`, a whole method, a loop body, an early return. Ignore lines that are unreachable by construction (see the dead-code rule).
2. **Read the test conventions.** Fetch `https://php-testo.github.io/llms.txt` and follow the `testo-write-tests` skill (assertions, lifecycle), `testo-data-driven` (`#[DataSet]` for branches/boundaries), and `testo-coverage` (`#[Covers]`). Match the surrounding test style exactly.
3. **Locate the existing tests.** Open the test file(s) listed above (the `covered_by` entries) — that's where the current coverage comes from and usually where the gaps belong. If the list is `none`, look under `{{TEST_DIR}}` for the segment's conventions and create a new test file following its naming/folder layout.

## Write the tests

- Cover the untested **behaviours**, not the line numbers. One focused test (or one `#[DataSet]` row) per distinct path.
- Every test MUST assert an **observable outcome** — a return value, a state change, a thrown exception (`Expect`/`#[ExpectException]`), an emitted event. A test that calls code but asserts nothing is **Risky in Testo, not Passed**, and proves nothing.
- For branchy logic (boundaries, flipped conditions, multiple `match` arms) add `#[DataSet]` rows rather than many near-duplicate methods.
- Add `#[Covers(...)]` per project policy: class-level when every test covers the same class, method-level when they differ.

## Gates — run in order, never skip one

Run tests from the project root.

### 1. PASS gate (no coverage — fast)

```
{{TEST_CMD}} --json --no-coverage --path="<yourTestFile>"
```

`--json` prints one compact object: `{ "status": "passed"|"failed", "totals": {...}, "failures": [...] }`. Your new tests **MUST pass** and **MUST NOT be Risky** (`status` passed, no risky entry). If risky → you forgot to assert; fix it. If failed → fix the test or the expectation.

### 2. COVERAGE gate — prove the lines are now hit

Re-collect coverage scoped to your test, then check the file:

```
{{TEST_CMD}} --coverage --json --coverage-clover={{OUT_DIR}}/focus.xml --path="<yourTestFile>"
{{PHP}} {{SKILL_DIR}}/scripts/coverage-report.php {{OUT_DIR}}/focus.xml --scope={{FILE}} --threshold=100
```

Read the printed report (or `{{OUT_DIR}}/coverage-report.md`): find the `{{FILE}}` row and its `uncovered_lines`. The lines you targeted **MUST no longer appear** there (and the file's `pct` must have risen). If a line you meant to cover is still listed, your test does not actually execute it — adjust the test (wrong branch, missing input, guard not tripped) and repeat from gate 1.

> The COVERAGE gate is the only proof of a kill. Tests passing in gate 1 does NOT mean the lines ran — a test can be green and still miss the path you targeted.

> **`#[Covers]` is required for attribution.** This project credits coverage to the production class a test declares it covers. If your test does **not** carry `#[Covers(\Fqn\Of\TheClass::class)]` (or a string FQN for a free function), the gate will show the lines **still uncovered even though they executed**. So before blaming the test logic on a failed gate, confirm the `#[Covers]` target matches `{{FILE}}`'s class.

## Hard rules — do not violate

- **No coverage theatre.** Never call a method just to colour its line green. If you can't think of a behaviour worth asserting on an uncovered line, leave it and say so in your report — do not write an assertion-free test.
- **Do NOT edit production code.** You only add/extend tests. If uncovered lines are genuinely **unreachable for any input** (dead code, defensive `@codeCoverageIgnore`-worthy guards, impossible branches), do NOT contort a test or delete the code — report `dead-code` with the lines and why, for the orchestrator to surface.
- Do NOT re-collect whole-project coverage or touch any other file's tests — the orchestrator runs the authoritative pass at the end.
- Do NOT use `git checkout`/tree-wide reverts — the working tree holds WIP (tests from earlier files).
- The focused `{{OUT_DIR}}/focus.xml` / `{{OUT_DIR}}/coverage-report.md` are scratch — overwriting them between files is fine; do not commit them.

## Report back — exactly this shape

```
file:    {{FILE}}  ({{PCT}}% -> <new pct from the coverage gate>%)
verdict: covered | partial | dead-code | blocked
tests:   <test file::methods added or strengthened, or "none">
lines:   <ranges now covered>  |  still uncovered: <ranges, with one-line reason each>
note:    <one line: the behaviours you asserted, or why the remainder is dead/unreachable>
```
