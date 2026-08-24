# Contributing to Testo

Thanks for your interest in improving Testo. This guide covers what we expect from a contribution.

## Onboarding

Requirements: PHP 8.2+ with Composer.

```bash
composer install   # also fetches dev binaries via dload
composer test      # run the test suite via the Testo CLI
```

See [tests/README.md](tests/README.md) for how self-tests and the suite layout work.

## Manual development

Additional local checks:

| Command            | What it does                                   |
| ------------------ | ---------------------------------------------- |
| `composer test`    | Run tests                                      |
| `composer test:cc` | Run tests with coverage → `runtime/clover.xml` |
| `composer rector`  | Apply Rector rules                             |
| `composer psalm`   | Static analysis                                |

For day-to-day work in an IDE, Testo ships the official [IDEA plugin `Testo`](https://plugins.jetbrains.com/plugin/28842-testo) (PhpStorm / IntelliJ) — the testing workflow you always wished your IDE had and never got.

## Before you open a PR

- **Understand the issue first.** Read it in full, and if anything is unclear, ask in the issue _before_ implementing — not after.
- **Signal your intent.** If you seriously plan to resolve an issue, say so in a comment first — even with no questions. Someone may already be working on it, it may be a draft that isn't meant to be built yet, or there may be discussion elsewhere you can't see. A one-line heads-up saves everyone the duplicate work — and it's often best to wait for a maintainer to confirm the approach before you start, rather than rework a finished PR.
- **Carry prior feedback forward.** If an earlier review of your PRs raised something, apply it to the next one. A mistake once is fine; repeating a fix that was already explained is not.

## Codebase rules

These apply to every contributor, human or agent:

- **Global / static state** — avoid it. Anything that works through globals or static variables (the Mockery container, error/exception handlers, container and messenger scopes) must be isolated within each test's boundaries — and since tests can run inside fibers, that isolation has to survive suspension: save and restore it around suspend/resume (the scope-swap in `\Testo\Application\Internal\MessengerHub::scope()` and `\Testo\Bridge\Mockery\Internal\MockeryInterceptor::run()`). All other test-specific context belongs on the interceptor context DTOs (`TestInfo` / `TestResult` attributes), not in globals.
- **Interceptors** — use the `InterceptorOptions::ORDER_*` constants (not magic numbers or `PHP_INT_MAX`) and get the priority right relative to the others (e.g. Assert, Lifecycle). An interceptor or finder that produces or targets a test type must declare `#[InterceptorOptions(testType: …)]`, or it leaks past `--type`.
- **Public vs internal API** — respect `@internal` / `@psalm-internal`, and mark extendable public classes `@api` rather than `final` (Psalm scans only `core/`, so `ClassMustBeFinal` misfires on classes meant to be subclassed).

## Working with AI assistants

You are the author of the change and own every line of it — exactly as if you had written it yourself. Reviewing it is your job, not the maintainer's; handing raw agent output to review shifts your responsibility onto someone else.

Using AI is not forbidden here — it's genuinely welcome when it raises the quality of your work. We build with it too, and the project even tracks its own [Vibe Index](https://github.com/roxblnfk/action-vibe-index). When AI helped on a change, feel free to credit it with an `Assisted-By:` commit trailer — see [docs/spec/commit-creation.md](docs/spec/commit-creation.md).

What isn't welcome is **raw AI output opened as a PR without a human review pass**: it's dismissive of the reviewer's time, and a maintainer can run an agent over the open issues just as easily. The value you add is the human review the agent can't do.

Before you publish, review the generated code yourself — read it, run it, and confirm it's correct. Pay special attention to the [codebase rules](#codebase-rules) above, which agents routinely get wrong.

If the agent produced something you don't fully understand, don't submit it — understand it first, or ask.

When you work through an agent, point it at [AGENTS.md](AGENTS.md) — the single source of truth for repo conventions: how the codebase is laid out, what to update when you change behavior (skills, docs), and how commits are made.

## What we look for

- Focused scope and a clear description of the change.
- Tests for new behavior. The kinds we write:
    - **Unit** — a class or function in isolation.
    - **Feature** — a behavior end-to-end through the pipeline (usually driven with `\Testo\Testing\Helper\TestRunner`).
    - **Self** — Testo exercising its own machinery, where a green `Status` _is_ the assertion (see [tests/README.md](tests/README.md)).
    - **Acceptance** — the user-facing surface (CLI, public API) as a user would hit it.
- Green CI — tests, code style, and static analysis all run there; the [onboarding](#onboarding) and [manual development](#manual-development) commands cover them locally.
- Public API or behavior changes reflected in the matching skills and docs (see [AGENTS.md](AGENTS.md)).
- Conventional-commit titles — release-please builds the changelog from them. See [docs/spec/commit-creation.md](docs/spec/commit-creation.md).
