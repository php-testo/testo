---
name: testo-run-tests
description: Run tests with the Testo CLI and act on the result. Use when executing a project's test suite or a subset (one test, one file, one suite, a group), when verifying a change didn't break tests, or when parsing a Testo run report. Triggers: "run the tests", "does it pass", "run only X", "re-run the failing test".
---

# Running Testo tests

Always run through the Testo CLI, and always with `--json`:

```
vendor/bin/testo --json
```

`--json` replaces the human progress stream with one compact JSON object on stdout: a run
summary plus the failed tests only. Passing, skipped, and risky tests collapse into counts, so
even a large suite costs almost no context. The human-readable terminal output is not a stable
interface — parse the JSON. When a human also needs terminal output (CI logs), use
`--log-json=build/report.json` instead: same JSON to a file, terminal output kept.

## Reading the report

```json
{
    "status": "failed",
    "duration": 1.42,
    "totals": {"total": 120, "passed": 118, "failed": 1, "error": 1},
    "failures": [
        {
            "test": "App\\Tests\\UserServiceTest::createsUser",
            "status": "failed",
            "exception": "Testo\\Assert\\AssertionFailedError",
            "message": "...",
            "file": "/app/tests/UserServiceTest.php",
            "line": 42,
            "trace": ["#0 /app/tests/UserServiceTest.php:42: ..."],
            "causedBy": [{"exception": "...", "message": "...", "file": "...", "line": 1}],
            "output": [{"channel": "stdout", "content": "..."}]
        }
    ]
}
```

- `status` — the run verdict. **Gate on `"status": "passed"`**, not on `failures` being empty:
  a run can be risky with zero failures.
- `totals` — counts keyed by lowercased test status (`passed`, `failed`, `error`, `skipped`,
  `risky`, `flaky`, `cancelled`, `aborted`); zero counts are omitted.
- `failures` — every failed/errored test with what you need to fix it: the throwable, its
  `previous` chain (`causedBy`), a stack trace trimmed at the test boundary, and captured
  output (`stdout`, log channels).
- `benchmarks` — present only when the run measured benchmarks (`#[Bench]`), omitted otherwise, so an
  ordinary run's payload is unchanged. One entry per benchmark test — per data set for a repeatable one,
  each carrying its `dataProvider`/`dataSet` — with `iterations`, the ranked `cases`, and `diagnostics`.

```json
"benchmarks": [
    {
        "test": "App\\Bench\\SortBench::compare",
        "iterations": 10,
        "cases": [
            {"name": "shift", "place": 1, "calls": 20, "mean": 0.26, "median": 0.27, "meanDiff": 0.0,
             "medianDiff": 0.0, "rstdev": 5.6, "filteredMean": 0.26, "filteredMeanDiff": 0.0,
             "filteredRstdev": 5.6, "rejected": 0, "memory": 4096}
        ],
        "diagnostics": [{"case": "shift", "kind": "HighVariance", "severity": "warning", "reason": "…", "advice": "…"}]
    }
]
```

A case ranks by `place` (1 is fastest); times are microseconds, each `…Diff` a percentage against the
`current` case (the `#[Bench]`-marked method) as the baseline — 0.0 for it, positive for slower,
negative for faster — `rstdev` the spread as a percent of the mean, the `filtered*` trio the same with
outliers dropped, and `memory` the peak bytes per iteration.

## Selecting what to run

Every filter is repeatable; different filters combine, each narrowing further:

```
vendor/bin/testo --json --suite=Unit                              # one suite (name from testo.php)
vendor/bin/testo --json --filter=UserServiceTest                  # by name fragment
vendor/bin/testo --json --filter='UserServiceTest::createsUser'   # one test method
vendor/bin/testo --json --path=tests/Unit/UserServiceTest.php     # one file
vendor/bin/testo --json --path='tests/Unit/User*'                 # glob: *, ?, [abc]
vendor/bin/testo --json --group=db --group=!slow                  # groups: OR to include, ! to exclude
vendor/bin/testo --json --type=!bench                             # types: test, inline, bench, profile
```

- `--filter` accepts `Class::method`, a FQN (class or function), or a bare fragment
  (method name, function name, or short class name).
- `--group` matches `#[Group('name')]`; `--type` matches how the test is declared —
  `test` (`#[Test]`), `inline` (`#[TestInline]`), `bench`, `profile`. For both, values
  OR together and a `!`-prefixed value excludes; exclusion wins over inclusion.
- If the project has `#[Bench]` benchmarks, they are slow — add `--type=!bench` unless the
  task is benchmarking (see the `testo-benchmarks` skill).

## Exit code and the empty run

The exit code is 0 only for a passed run. A run that discovers **zero tests** is not green:
it reports `"status": "risky"` with `"total": 0` and exits non-zero. The usual cause is an
over-narrow filter — widen it (a typo in `--filter`, a `--path` that matches nothing, a
`--suite` name that doesn't exist in `testo.php`).

## Beyond a plain run

- Coverage (`--coverage`, `--coverage-clover=`, `--coverage-level=`, …) — see the
  `testo-coverage` skill.
- `--log-junit=`, `--log-html=` (a `.html` path → single file, anything else → directory;
  bare `--log-html` → `runtime/report`),
  `--log-report=` (the full run as a versioned JSON document — the data behind the HTML),
  `--teamcity` — reports for CI and IDEs, not for agent parsing.
- `--config=path/to/testo.php` when the config is not at the project root.
- Full flag list: `vendor/bin/testo --help`.
