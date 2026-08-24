# Commit Creation Spec

How to create commits in this repository: what goes into one, how the message is structured, and the conventions release tooling relies on.

Testo uses [release-please](https://github.com/googleapis/release-please) to build `CHANGELOG.md`, version tags, and releases automatically — from **PR titles** (squash-merge commits). If the title doesn't follow the conventions below, the change won't land in the changelog or won't bump the right version.

## What goes into a commit

- **One logical change per commit.** Unrelated edits (a feature + an unrelated style cleanup) belong in separate commits; see [Several changes in one commit](#several-changes-in-one-commit) for the rare case where they must share one.
- **Stage intentionally.** Inspect `git status` and `git diff` before committing; stage only files meant for this change. Never commit secrets, credentials, or local-only artifacts (`vendor/`, IDE files, binaries that aren't part of the change).
- **Verify first.** Run the relevant checks (tests, code style, static analysis) before committing, not after — a commit is a claim that the tree at that point works.

## Message structure

A commit message has three parts, separated by blank lines:

```
<subject>            # conventional line(s) — see Format
                     # (blank line)
<body>               # optional: motivation, context, trade-offs
                     # (blank line)
<trailers>           # optional: Assisted-By:, BREAKING CHANGE:, etc.
```

Git folds an entire first paragraph into the subject, so anything after the first line must sit below a blank line or it will be swallowed into the subject.

## Format

```
<type>[(<scope>)][!]: <imperative summary>
```

- **`type`** — one of the types below. Required.
- **`scope`** — optional; see [Scopes](#scopes).
- **`!`** — marks a breaking change (alternative: a `BREAKING CHANGE:` footer in the commit body).
- **Summary** — imperative mood, no trailing period: "add", not "added" or "adds". Lowercase after the colon is the norm here, but capitalization is not enforced.

Examples:

```
feat(fiber): allow #[RunInFiber] on free functions
fix(data): correctly bind non-static DataProvider methods to their instance
feat(codecov)!: announce every coverage report; add CoverageReport::info()
chore: cleanup
```

## Types

Types marked **visible** appear in the changelog; hidden ones are tracked but omitted from its sections (config: [.github/.release-please-config.json](../../.github/.release-please-config.json)).

| Type | Visible | Use for |
|---|---|---|
| `feat` | yes | New user-facing capability → bumps minor version |
| `fix` | yes | Bug fix → bumps patch version |
| `perf` | yes | Performance improvement |
| `docs` | yes | Documentation only |
| `deps` | yes | Dependency updates |
| `refactor` | yes | Code change that neither fixes nor adds behavior |
| `test` / `tests` | no | Test-only changes |
| `build` | no | Build system, composer config |
| `ci` | no | CI pipelines |
| `style` | no | Formatting, code style |
| `chore` | no | Maintenance, tooling, releases |
| `revert` | no | Reverting a previous commit |

Versioning notes:

- Only `feat`, `fix`, and breaking changes (`!`) affect the version number.
- A breaking change on any type bumps the major version (or minor while pre-1.0).

## Scopes

The repo is a monorepo: root package (`testo/testo`), plugins under [`plugin/`](../../plugin/), and integrations under [`bridge/`](../../bridge/) each get their own changelog and release.

**Prefer the package/component name as the scope**: `core` (root package code), or a plugin/bridge name — `assert`, `codecov`, `repeat`, `filter`, `retry`, `lifecycle`, `data`, `inline`, `bench`, `fiber`, `facade`, `test`, `convention`, `rector`, `infection`, `mockery`, `revolt`, `vcr`, `symfony-console`.

For cross-cutting internal areas a functional scope is fine when no single package fits: `output`, `renderer`, `terminal`, `report`, `messenger`, `pipeline`, `testing`, `skills`, `spec`. When nothing fits, omit the scope (`chore: cleanup`).

A PR that spans several packages: pick the dominant scope, or go scope-less if truly mixed.

## Several changes in one commit

A commit carrying several distinct changes lists each one as its own conventional line. Take the types and scopes from the repository's own `git log` rather than inventing a vocabulary:

```
feat(foo): bar
test(foo): baz
```

The first line is the subject; the rest open the body, below the blank line that closes the subject. Git folds an entire first paragraph into the subject, so a second line placed directly under it would be swallowed — keep the blank line between them.

Release tooling parses those lines, so an accurate type puts each change in the right changelog section.

## Attribution trailer

When AI assisted with a change, end the commit message with an `Assisted-By:` trailer on its own last line after a blank line (see [CONTRIBUTING.md](../../CONTRIBUTING.md)):

```
Assisted-By: Claude Opus 5 <noreply@anthropic.com>
```

Name whichever model did the work and keep the `<noreply@anthropic.com>` address. This is the commit's one attribution trailer — it stands in for `Co-Authored-By:`, which forges read as a real co-author and count in contributor statistics; `Assisted-By:` records the same assistance without that side effect.

## PR titles

Release-please reads the **PR title**, so even messy branch commits are fine as long as the squash-merge PR title follows this format. Keep one logical change per PR so the type/scope is honest.
