# AGENTS.md

Instructions for AI coding agents (Claude Code, Codex, Aider, Cursor, …).

This file is the **single source of truth** for agent instructions in this repository. It covers both:

- **Users of Testo** writing tests in their own projects (sections up to *Contributing to Testo itself*),
- **Contributors to Testo itself** (the last section).

Note: this file is shipped to downstream users of the `testo/testo` package — the contributor-only material lives at the bottom so user-facing guidance comes first.

If you are contributing **to Testo itself**, read the whole file including [Contributing to Testo itself](#contributing-to-testo-itself) and [tests/README.md](tests/README.md).

## What Testo is

**Testo** is an extensible PHP testing framework (PHP 8.2+) for projects that need substantial customization of testing workflows — SDKs, framework tooling, complex integrations. It uses familiar PHP syntax (no DSL) with attribute-based test configuration and a middleware system for deep customization.

Core surface you will encounter:

- Attributes: `#[Test]`, `#[RetryPolicy]`, `#[ExpectException]`, and others.
- No base class requirement for test classes.
- Built-in DI container; assertions via a fluent `Assert` facade.
- Symfony Console-based CLI (`vendor/bin/testo`).
- Configuration lives in a `testo.php` file at the project root.

Philosophy: the name derives from East/South Slavic "testo" (dough) — symbolizing malleability. Developers deserve complete authority over their testing environments; a minimal core stays powerful through extensions (middleware architecture).

## Required reading before writing or modifying Testo tests

Testo publishes machine-friendly docs in the `llms.txt` format. **Fetch the appropriate one before writing tests, middleware, or extensions** — they describe the public API faithfully, which is hard to reconstruct from source alone.

- **<https://php-testo.github.io/llms.txt>** — concise index of Testo's public API: attributes, assertions, configuration entry points. Use this for routine test-writing tasks.
- **<https://php-testo.github.io/llms-full.txt>** — full expanded documentation: middleware architecture, plugin authoring, dependency injection, console commands, lifecycle hooks, etc. Use this when extending Testo or doing non-trivial customization.

Rule of thumb: start with `llms.txt`; escalate to `llms-full.txt` when the short index does not answer the question. Prefer these sources over guessing from class names or older PHPUnit knowledge — Testo is **not** PHPUnit and the APIs differ.

## Pitfalls to avoid

- **Do not assume PHPUnit semantics.** Testo's `Assert` facade, lifecycle, data providers, and exception expectations are its own. Verify against `llms.txt` before writing.
- **Do not mock enums or `final` classes** — use real instances.
- **Do not invent attributes.** If you need behavior that is not in `llms.txt`, look in `llms-full.txt` before inventing an attribute or middleware that does not exist.
- **Run tests via the Testo CLI** (`vendor/bin/testo`). For programmatic parsing add `--json` — it writes a structured JSON report to stdout; human-readable terminal output is not a stable interface.

# Contributing to Testo itself

## Important

- Use testo-* skills to write tests in a good way.
- About Self-Tests read [tests/README.md](tests/README.md).
- The [`skills/`](skills/) directory ships AI-agent skills (`SKILL.md` files) that document Testo's public surface for coding agents. When you change behavior or add public API — new attributes, exceptions, statuses, configuration options, plugin hooks — check the matching skill(s) listed in [skills/README.md](skills/README.md) and update them in the same change. Skills are part of the public contract: if production code and skills disagree, downstream agents will write wrong code.
- When you add a new user-facing feature (a public attribute, assertion, lifecycle hook, exception, etc.), check whether the [`testo/bridge-rector`](bridge/rector/) conversion rules need a counterpart — a feature users write in their tests almost always has a PHPUnit/Pest equivalent to convert to/from. If a faithful conversion exists, add the Rector rule (+ co-located `*.php.inc` fixtures) in the relevant direction set and update [`bridge/rector/FEATURE_PARITY.md`](bridge/rector/FEATURE_PARITY.md); if it genuinely can't be converted, record it as a documented stub + `TODO.md` entry rather than leaving it silently unhandled.
- Conventional-commit titles — release-please builds the changelog from them. Follow [docs/spec/commit-creation.md](docs/spec/commit-creation.md).

## Core features

- Attribute-based test configuration (`#[Test]`, `#[RetryPolicy]`, `#[ExpectException]`)
- Memory leak detection capabilities
- Retry policies for flaky tests
- Flexible assertion library

## Domain Glossary

- **Test** — a single test method (one `#[Test]` method, function, or `#[TestInline]` case).
- **Test Case** — file-scope group of tests: methods of one class, or functions of one file. A file with several test classes yields several Test Cases.
- **Test Suite** — a named, configured collection of Test Cases (`SuiteConfig`). Suite is the smallest unit that plugins can be applied to — different suites can have different plugin sets.

Event hierarchy: `Session` → `Worker` → `TestSuite` → `TestCase` → `TestPipeline` → `TestBatch` → `Test`. See [docs/spec/events-naming.md](docs/spec/events-naming.md).
