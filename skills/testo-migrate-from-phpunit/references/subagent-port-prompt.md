<!--
  Fixed subagent prompt for the porting pass of testo-migrate-from-phpunit
  (Approach A Stage A5, Approach B Stage B3).
  The orchestrator fills every {{PLACEHOLDER}} and hands the rest to the subagent VERBATIM.
  Do NOT paraphrase the procedure or the hard rules — paraphrasing is how the argument-order
  flip and the assert-something / PASS-gate steps get silently dropped.

  Placeholders:
    {{FILE}}      the test file to port                       (batch entry: path)
    {{NEEDS}}     the ordered to-do list for this file        (batch entry: needs[], one per line)
    {{HINTS}}     short hints keyed by construct              (batch entry: hints{}, "key: hint" per line)
    {{MAP}}       absolute path to references/phpunit-to-testo-map.md
    {{TEST_CMD}}  php binary + vendor/bin/testo, e.g. "C:\php\php.exe" vendor/bin/testo
                  Do NOT bake in --json / --path — the template adds them itself.
    {{TEST_DIR}}  the test directory this file lives under (for context / shared fakes)
-->

You are porting **one** PHPUnit test file to Testo. Work on this single file only — do not touch any
other test file, production code, or `testo.php` (the orchestrator owns the suite config).

## Target

- File: `{{FILE}}`
- This file's to-do list (do every item, in order — structural first):

{{NEEDS}}

- Hints:

{{HINTS}}

## Authoritative mapping — READ IT FIRST

Read `{{MAP}}` before editing. It is the source of truth for every PHPUnit → Testo construct, the
worked example, and the pitfalls. Also fetch `https://php-testo.github.io/llms.txt` if you are unsure
about an attribute. Do **not** invent Testo API from memory.

## Running tests

**Always run with `--json`.** It prints one compact JSON object (run summary + failures only) instead
of the verbose tree, and writes nothing to disk:

```
{{TEST_CMD}} --json --path="{{FILE}}"
```

Read the verdict straight from the JSON:

```json
{ "status": "passed", "totals": { "total": 3, "passed": 3 }, "failures": [] }
```

`status` is `"passed"` or `"failed"`; `failures[]` names failing tests. (Exit code is non-zero on failure.)

- NEVER add `--log-junit`, `--coverage-*`, or any report flag — `--json` to stdout is all you need.

## Procedure — follow in order, never skip a step

1. **READ** `{{FILE}}` and the mapping. Identify every construct in the file that the to-do list and
   mapping cover.
2. **PORT** the file in place, applying the mapping:
   - Remove `extends TestCase`; add a class-level `#[Test]` when every public method is a test, else
     per-method `#[Test]`. Rename `testFoo()` → `foo()`.
   - Convert assertions to `Assert::*`. **The comparison argument order FLIPS**:
     `assertSame($expected, $actual)` → `Assert::same($actual, $expected)`. `assertCount(3, $c)` →
     `Assert::count($c, 3)`. `assertContains($needle, $hay)` → `Assert::contains($hay, $needle)`.
     Double-check every comparison — this is the #1 silent error.
   - `setUp`/`tearDown` → `#[BeforeTest]`/`#[AfterTest]`; the static class hooks →
     `#[BeforeClass]`/`#[AfterClass]`.
   - `expectException(...)` (before the Act) → `Expect::exception(...)->withMessage(...)`; method
     return type becomes `never`. It MUST precede the triggering call.
   - `@dataProvider`/`#[DataProvider]` → `#[DataProvider('m')]` (provider `public static`, returns
     `iterable`, labelled rows). `@testWith`/`#[TestWith]` → repeated `#[DataSet([...], 'label')]`.
   - `@group`/`#[Group]` → one variadic `#[Group(...)]` from `Testo\Filter\Group`.
   - `@covers`/`#[CoversClass]` → `#[Covers(...)]`; `markTestSkipped` → `throw new SkipTest(...)`.
   - Mocks: replace with a hand-rolled fake (preferred) — never mock `final`/enums. If the file uses
     a kept mock library, leave it but make it run under Testo. If a fake is non-trivial and the
     orchestrator told you a shared fake exists, use it; do not invent a divergent copy.
   - Fix imports: drop `PHPUnit\…`; add `Testo\Assert`, `Testo\Test`, `Testo\Expect`,
     `Testo\Data\DataProvider`/`DataSet`, `Testo\Filter\Group`, `Testo\Lifecycle\…`, `Testo\Codecov\Covers`
     as used.
3. **PASS GATE.** Run the file (command above). It **MUST** report `status: "passed"` with the
   expected test count. If `failed`, read `failures[]`, fix the port, and repeat. Do not stop on red.
4. **ASSERT-SOMETHING CHECK.** Confirm every ported test makes at least one real assertion (or
   `Expect::exception`, or is intentionally `#[ExpectNoAssertions]`). A test that runs code but
   asserts nothing is `Status::Risky` in Testo — that is a failed port, not a pass.

## Hard rules — do not violate

- **Argument order flips on comparisons.** `($expected, $actual)` → `($actual, $expected)`. Verify each one.
- **A port is done only when the file is GREEN under `{{TEST_CMD}} --json` with real assertions.** The
  old PHPUnit suite passing proves nothing about the Testo port.
- **Never edit `testo.php`** or any file other than `{{FILE}}` (plus a shared fake the orchestrator
  explicitly assigned you). Do NOT `git checkout`/`git restore` anything — the working tree holds
  other subagents' ports.
- **Don't keep `extends TestCase` "just in case"** — the class won't be discovered as a Testo test.
- **Don't fake a pass.** If the test genuinely cannot be ported (needs a mock you can't fake, a regex
  exception matcher with no literal substring, a PHPUnit-only constraint), STOP and report `blocked`
  with the reason — do not delete the test or weaken it into a no-op.

## Report back — exactly this shape

```
file:    {{FILE}}
verdict: ported | ported-partial | blocked
tests:   <N green> / <N total in file>
note:    <one line: what you converted, or — for partial/blocked — exactly what remains and why>
```
