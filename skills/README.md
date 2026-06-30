### Skills for Testo users

A curated set of AI skills for projects that use **Testo** as their testing framework.
Each subdirectory contains a `SKILL.md` (Anthropic Skill format: frontmatter + Markdown body)
that an AI coding agent can load on demand.

| Skill | Use when… |
|---|---|
| [`testo-write-tests`](testo-write-tests/SKILL.md) | Writing or modifying a normal test class — covers `#[Test]`, `Assert`, `Expect`, lifecycle hooks. |
| [`testo-data-driven`](testo-data-driven/SKILL.md) | Parameterizing a test — `#[DataSet]`, `#[DataProvider]`, `#[DataZip]`, `#[DataCross]`. |
| [`testo-flaky-tests`](testo-flaky-tests/SKILL.md) | Stabilizing flaky tests with `#[Retry]` or stress-testing with `#[Repeat]`. |
| [`testo-inline-tests`](testo-inline-tests/SKILL.md) | Attaching `#[TestInline]` examples directly to production methods. |
| [`testo-benchmarks`](testo-benchmarks/SKILL.md) | Writing or tuning `#[Bench]` benchmarks. |
| [`testo-coverage`](testo-coverage/SKILL.md) | Configuring `CodecovPlugin`, reports, and `#[Covers]`. |
| [`testo-increase-coverage`](testo-increase-coverage/SKILL.md) | Raising line coverage — collect coverage, rank the least-covered files into a work-list, and write tests for the gaps with subagents. |
| [`testo-mutation-testing`](testo-mutation-testing/SKILL.md) | Running mutation testing with Infection — collecting surviving mutants efficiently and killing them. |
| [`testo-migrate-from-phpunit`](testo-migrate-from-phpunit/SKILL.md) | Porting an existing PHPUnit (or Pest) suite to Testo — phased, with a Rector-assisted path (`testo/bridge-rector`) and an AI-agent rewrite path. |
| [`testo-plugin-author`](testo-plugin-author/SKILL.md) | Authoring a Testo plugin (events, interceptors, container bindings). |
| [`testo-configure`](testo-configure/SKILL.md) | Setting up or editing `testo.php` (suites, finder, plugins). |

### Authoritative source of truth

All skills point at the machine-readable Testo docs and tell the agent to fetch them before writing code:

- **<https://php-testo.github.io/llms.txt>** — concise index of the public API.
- **<https://php-testo.github.io/llms-full.txt>** — full reference (middleware, plugin authoring, DI, console).

Skills encode *when* to act and *what shape* the answer should take; the authoritative API
text lives in `llms.txt`. This keeps skills small and resistant to API drift.

### Installing into a project

Copy the directories you want into the project's `.claude/skills/` (or any skills root the
agent is configured to read). Each skill is self-contained — no shared files between them.
