# Skills: group by direction, not by plugin

## Problem

`skills/` holds 14 skills; nine of them describe exactly one plugin (`testo-flaky-tests` is
Retry + Repeat, `testo-coverage` is Codecov, `testo-inline-tests` is Inline, and so on). The agent
picks a skill by *what it is doing*, not by *which package is installed*, so a per-plugin split
makes it guess the package first. Every extra skill also adds a permanently loaded description to
the agent's context, and every new plugin threatens to add one more.

`testo-test-doubles` is the shape to copy: one skill for one direction ("isolate a collaborator"),
a short `SKILL.md` that routes, and one file per approach under `references/` (Double, Mockery,
hand-written). The plugin is a detail inside the direction.

## Target layout

| Direction | Skill | `SKILL.md` holds | `references/` |
|---|---|---|---|
| Run the suite | `testo-run-tests` | as is | |
| Configure `testo.php` | `testo-configure` | as is, plus a table of optional plugins pointing at the skill that covers each | |
| Write a test | `testo-write-tests` | `#[Test]`, `Assert`, `Expect`, skip/cancel, lifecycle hooks, groups | `data-driven.md` (DataSet, DataProvider, DataZip, DataCross), `inline-tests.md` (`#[TestInline]`) |
| Isolate a collaborator | `testo-test-doubles` | as is | as is |
| Keep tests reliable and clean | `testo-test-hygiene` (new) | the symptom table below, routing | `flaky.md` (Retry, Repeat), `php-errors.md` (error-handler plugin, `#[ExpectErrorHandlerChange]`), `leaks.md` (`Expect::leaks` / `notLeaks`, process-global state) |
| Test async code | `testo-async` | as is (already two approaches in one skill) | |
| Measure performance | `testo-benchmarks` | as is | |
| Measure test quality | `testo-test-quality` (merge) | routing between the three | `coverage.md` (CodecovPlugin, levels, reports, `#[Covers]`), `increase-coverage.md` + its `scripts/`, `mutation-testing.md` + its `scripts/` |
| Migrate from PHPUnit | `testo-migrate-from-phpunit` | as is | as is |
| Extend Testo | `testo-plugin-author` | as is | as is |

Fourteen skills become ten. Skills removed: `testo-data-driven`, `testo-inline-tests`,
`testo-flaky-tests`, `testo-coverage`, `testo-increase-coverage`, `testo-mutation-testing`,
`testo-php-errors` (folded into hygiene). `testo-async` and `testo-benchmarks` stay separate: each
is a direction of its own, and merging them anywhere loses the trigger words.

## Rules for a direction skill

- **`SKILL.md` routes; `references/` explains.** The top file answers "which approach, and why"
  in one table and states what every branch needs (fetch `llms.txt`, run from the project root).
  Everything only one branch needs goes into that branch's reference file.
- **Description carries every trigger the merged skills had.** Merging `testo-flaky-tests` into
  hygiene must keep "flaky", "intermittent failure", "retry", "rerun" in the description, or the
  agent stops reaching the material. One trigger per branch; drop synonyms.
- **Quote the description** in the frontmatter whenever it contains `#[Attr]`: YAML cuts an
  unquoted value at ` #`.
- **A reference file is self-contained for its branch.** The agent loads one reference, not all
  of them; a reference may not depend on a sibling being read first.
- **Symptom-first routing.** The user names a symptom ("test passes but a warning scrolls by",
  "green locally, red in CI"), not a plugin. The routing table maps symptoms to references.

## `testo-test-hygiene` routing table (draft)

| Symptom | Reference |
|---|---|
| Fails sometimes, passes on rerun; "verify the fix sticks" | `flaky.md` |
| A PHP warning, notice or deprecation appears during a test and nothing fails; deprecations must fail the build; tested code installs its own error handler | `php-errors.md` |
| Memory grows across tests; an object must not survive the test; a static or global keeps state | `leaks.md` |

## `testo-test-quality` routing table (draft)

| Intent | Reference |
|---|---|
| Turn coverage on, pick a level, produce a report, scope with `#[Covers]` | `coverage.md` |
| Raise the number: find the least-covered files and write tests for them | `increase-coverage.md` |
| Check whether the tests would notice a bug: Infection setup, MSI, killing mutants | `mutation-testing.md` |

## Order of work

1. `testo-test-hygiene`: create it from `testo-flaky-tests` and the standalone
   `testo-php-errors` skill; write `leaks.md` from `Expect::leaks` in `plugin/assert`. Delete the
   two source skills.
2. `testo-write-tests`: move the data-driven and inline material into `references/`; keep a
   one-line pointer per reference in the body where the topic would otherwise appear. Delete the
   two source skills.
3. `testo-test-quality`: merge the coverage trio; move each skill's `scripts/` under the merged
   skill and fix the relative paths the references cite.
4. `testo-configure`: add the optional-plugin table.
5. `skills/README.md`: rewrite the table to the ten skills. `AGENTS.md` names
   `skills/README.md` as the index, so nothing else needs to change.

Each step is one commit and leaves every skill in the table loadable on its own. After each step
load every touched skill once (`composer install` re-syncs `.agents/skills` through `llm/skills`)
to confirm its frontmatter still parses and its `references/` paths resolve.
