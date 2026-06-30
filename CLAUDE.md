## Important

- Use testo-* skills to write tests in a good way.
- About Self-Tests read [tests/README.md](tests/README.md)
- [AGENTS.md](AGENTS.md) is shipped to downstream users of the `testo/testo` package — keep it user-facing, not contributor-facing. This file (`CLAUDE.md`) is the contributor-facing entry point and is **not** included in `composer archive`.
- The [`skills/`](skills/) directory ships AI-agent skills (`SKILL.md` files) that document Testo's public surface for coding agents. When you change behavior or add public API — new attributes, exceptions, statuses, configuration options, plugin hooks — check the matching skill(s) listed in [skills/README.md](skills/README.md) and update them in the same change. Skills are part of the public contract: if production code and skills disagree, downstream agents will write wrong code.
- When you add a new user-facing feature (a public attribute, assertion, lifecycle hook, exception, etc.), check whether the [`testo/bridge-rector`](bridge/rector/) conversion rules need a counterpart — a feature users write in their tests almost always has a PHPUnit/Pest equivalent to convert to/from. If a faithful conversion exists, add the Rector rule (+ co-located `*.php.inc` fixtures) in the relevant direction set and update [`bridge/rector/FEATURE_PARITY.md`](bridge/rector/FEATURE_PARITY.md); if it genuinely can't be converted, record it as a documented stub + `TODO.md` entry rather than leaving it silently unhandled.

## Project Overview

**Testo** is an extensible PHP testing framework designed for projects requiring substantial customization of testing workflows.

### Philosophy
- Name derived from East and South Slavic languages "testo" (dough) - symbolizing malleability and customization
- Core principle: developers deserve complete authority over their testing environments
- Built on minimal core with middleware system for unprecedented extensibility

### Target Audience
Projects requiring significant testing workflow customization:
- SDK development
- Framework tools and libraries
- Complex integrations
- Scenarios where PHPUnit/standard frameworks lack flexibility

### Key Differentiators
1. **Familiar PHP syntax** - No new DSL to learn, standard PHP code
2. **Extensibility first** - Middleware architecture enables deep customization
3. **Minimal core** - Lightweight foundation that remains powerful through extensions

### Core Features
- Attribute-based test configuration (#[Test], #[RetryPolicy], #[ExpectException])
- No base class requirement for test classes
- Built-in dependency injection support
- Memory leak detection capabilities
- Retry policies for flaky tests
- Flexible assertion library
- Symfony Console-based CLI

### Technical Stack
- PHP 8.1+ (leverages modern language features)
- Symfony components (Console, Finder, Process)
- ReactPHP for async operations
- PSR standards compliance (Container, SimpleCache)

## Domain Glossary

- **Test** — a single test method (one `#[Test]` method, function, or `#[TestInline]` case).
- **Test Case** — file-scope group of tests: methods of one class, or functions of one file. A file with several test classes yields several Test Cases.
- **Test Suite** — a named, configured collection of Test Cases (`SuiteConfig`). Suite is the smallest unit that plugins can be applied to — different suites can have different plugin sets.

Event hierarchy: `Session` → `Worker` → `TestSuite` → `TestCase` → `TestPipeline` → `TestBatch` → `Test`. See [docs/spec/events-naming.md](docs/spec/events-naming.md).
