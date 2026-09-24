---
name: testo-test-doubles
description: 'Isolate a collaborator in a Testo test with a test double — pick the right kind (dummy, stub, spy, mock, fake, partial) and build it with Double (testo/bridge-double), Mockery (testo/bridge-mockery), or a hand-written class — and place any fixture class a test declares. Use when the user says "mock", "stub", "spy", "fake", "test double", "in-memory repository", "isolate the dependency", "createMock", "partial mock", "verify it was called", "should receive"; when a test needs a collaborator that touches I/O, time, randomness, or a third-party service; or when a test declares a helper class of its own and it needs a home.'
---

# Test doubles in Testo

Testo core ships **no doubling facility**. A test isolates a collaborator through one of three routes,
each with its own reference next to this file:

| Route | Reference | Reach for it when |
|---|---|---|
| **Hand-written** fake/stub/spy class | `references/handwritten.md` | **The default**, library installed or not: a real class under `tests/` reads without a DSL, is reused across tests, survives a library swap, and holds state. |
| **Double** (`testo/bridge-double`) | `references/double.md` | A library earns its place: a wide third-party interface, call order as the contract, or the surrounding tests already double this way. Choosing fresh on PHP 8.3+. |
| **Mockery** (`testo/bridge-mockery`) | `references/mockery.md` | The same cases, when the project already uses Mockery or must stay on PHP 8.2. |

Whichever route you take, every class the test itself declares gets a file and a directory:
`references/placement.md`. That reference answers the fixture case too, which is where a test lands
when no double is warranted at all.

Run every command from the project root.

## Vocabulary

A **test double** is any stand-in for a real collaborator. Mocks are one kind, not a synonym. The ladder,
from least to most knowledge about the interaction:

| Kind | Does | Verifies interaction? | Reach for it when |
|---|---|---|---|
| **Dummy** | Fills a parameter, is never called | No | The SUT needs *an* instance and nothing else |
| **Stub** | Returns canned answers | No — you assert on the SUT's result | You only need to feed a value in |
| **Spy** | Stub that records calls; you inspect them **after** the act | Yes, post hoc | "Was it called, with what?" matters and the test should read Arrange → Act → Assert |
| **Mock** | Expectations declared **before** the act, checked at teardown | Yes, up front | Call count or order *is* the contract |
| **Fake** | A working simplified implementation (in-memory repository, fixed clock) | No — behaves for real | The collaborator is stateful or used by many tests |

**Fixture** — the category that is *not* on this ladder. A fixture is a class the test declares as
**material** rather than as a stand-in: a synthetic value object it feeds the SUT, a target class a data
provider drives, an interface written to be doubled, a subclass of the SUT that exists to be recognised.
It replaces nothing, so no rung fits it, and it is placed rather than built — `references/placement.md`.
Misreading a fixture as "just a helper" is what leaves it stranded at the foot of a test file.

Orthogonal axes, independent of the ladder:

- **Loose vs strict** — a loose double answers unconfigured calls with a safe default; a strict one throws
  on them. Default loose; go strict when an unexpected call is itself the bug you are hunting.
- **Full vs partial** — a partial replaces some methods and runs the real code for the rest. Rare and a
  smell: it usually means the class under test is doing two jobs.
- **Verification point** — both bridges verify at teardown for every test and turn an unmet expectation
  into `Status::Failed` (not `Aborted`). There is no `Mockery::close()` / `verify()` boilerplate to write.

## Step 1 — Pick the kind

Choose the lowest rung that expresses the contract:

1. **Real object first.** Value objects, DTOs, enums, `final` classes, pure functions: instantiate them.
   Never double these. A real object the test had to invent is a fixture: `references/placement.md`
   gives it a file.
2. **Stub** when the test asserts on what the SUT returns or does with the value.
3. **Spy** when the test asserts on how the collaborator was used. Prefer it over a mock: the check sits
   in the Assert phase where the reader expects it.
4. **Mock** only when the number or order of calls is the behaviour under test (`save` called exactly once,
   `open` before `write`).
5. **Fake** when the collaborator is stateful, a port you own, or when three or more tests would otherwise
   configure the same stub. Reference: `references/handwritten.md`.

Double only *collaborators*, never the system under test. A test that doubles the class it is testing
tests the double. A subclass of the SUT that exists only to be observed is a fixture, not a double.

## Step 2 — Pick the tool

Run the pre-flight. It reads only `composer.json`, `vendor/` and `testo.php`, so it needs no confirmation:

```bash
php <skillDir>/scripts/precheck.php          # add --root=PATH when not at the project root
```

`<skillDir>` is this skill's own directory (the folder holding `SKILL.md`). It prints, per library, whether
the library and its Testo bridge are installed and whether the plugin is registered in `testo.php`, then a
verdict:

Whatever it prints, a hand-written class (`references/handwritten.md`) stays the first choice; the verdict
says which library is available for the cases that earn one:

- **DOUBLE: READY** → `references/double.md` for those cases.
- **MOCKERY: READY** → `references/mockery.md` for those cases.
- Both READY → the one the surrounding tests already use; for a fresh file prefer Double.
- Neither → offer to install a bridge only when the user asks for a mocking library or the test would need
  three or more behaviour-verifying doubles; the install steps live in the matching reference. A project
  stays on **one** library — never add a second.

A library installed **without its bridge plugin registered** is the trap the pre-flight exists for:
expectations then silently go unverified and a mock-only test comes out `Status::Risky`. Fix registration
before writing the test (steps in the reference).

## Rules shared by every route

- **Type the variable as an intersection** so static analysis sees both the real contract and the double's
  verbs: `/** @var DoubleInterface&Repository $repo */` (Double) or `/** @var MockInterface&Repository $repo */`
  (Mockery).
- **A double-only test still counts as asserting.** Both bridges mirror verified expectations into the
  Assert history, so a test whose only checks are `expects()` / `shouldHaveReceived()` passes, not Risky.
  A *hand-written* spy has no such hook: assert on its recorded calls with `Assert::*`.
- **Argument matching is strict.** Double compares scalars with `===`; Mockery's `with()` is loose (`==`)
  but its object matching is by identity. When porting between the two, re-check every `with()`.
- **No static / magic-method doubling.** Neither library doubles static methods; Double allows only
  `__invoke`, `__toString`, `__serialize`, `__unserialize`, `__clone`. Wrap the static call in an
  instance you can double.
- **Fibers are safe.** Both bridges park their process-global state on every suspension, so doubles work
  under `#[RunInFiber]` / `#[RunInRevolt]` (see `testo-async`).
- **Migrating doubles?** `testo/bridge-rector` converts them mechanically: PHPUnit `createMock()` chains
  to Double (`PHPUNIT_TO_DOUBLE`) or Mockery (`PHPUNIT_TO_MOCKERY`), and Mockery to Double
  (`MOCKERY_TO_DOUBLE`), all in `TestoRectorSetList`. The rest of a PHPUnit migration is in
  `testo-migrate-from-phpunit`.

## Related skills

- `testo-write-tests` — `#[Test]`, `Assert`, `Expect`, lifecycle hooks the double sits inside.
- `testo-configure` — where `plugins:` live in `testo.php` when registering a bridge.
- `testo-async` — fiber-driven tests that hold doubles across suspensions.
- `testo-migrate-from-phpunit` — Rector-assisted PHPUnit migration, mocks included.
