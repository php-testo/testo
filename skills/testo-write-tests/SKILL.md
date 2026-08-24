---
name: testo-write-tests
description: Write or modify tests in a project that uses the Testo PHP testing framework. Use when adding a #[Test] class, writing assertions with the Assert facade, expecting exceptions with Expect, or adding lifecycle hooks (#[BeforeTest], #[AfterTest], #[BeforeClass], #[AfterClass]). Trigger when the user says "write a test", "add a test for X", "test this class", or edits a file under `tests/`.
---

# Writing tests with Testo

The attribute set, assertion facade, exception expectations, and lifecycle hooks are Testo's own.
Write them the Testo way described below — don't transliterate idioms from other test frameworks.

## Before you write code

Fetch the canonical API surface (cached for 15 min):

- `https://php-testo.github.io/llms.txt` — concise index. Always start here.
- `https://php-testo.github.io/llms-full.txt` — escalate when `llms.txt` doesn't answer the question.

If the project ships an `AGENTS.md`, honour it.

## Canonical shape of a test class

```php
<?php
declare(strict_types=1);

namespace Tests\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use App\UserService;

#[Test]
#[Covers(UserService::class)]
final class UserServiceTest
{
    public function createsUserWithGivenName(): void
    {
        $service = new UserService(new InMemoryRepository());

        $user = $service->create('Alice', 'alice@example.com');

        Assert::same('Alice', $user->name);
    }
}
```

Hard rules:

- Class-level `#[Test]` when every public method is a test (preferred). Method-level `#[Test]` when only some are.
- `final class` by default.
- No base class — Testo does not require one.
- Public methods returning `void` or `never` under a `#[Test]` class are auto-discovered as tests.
- One `#[Covers(...)]` at class level when all tests cover the same class; at method level when they differ.
- Arrange / Act / Assert separated by a single blank line. Do **not** write `// Arrange`, `// Act`, `// Assert` comments.
- File path mirrors the source: `src/Foo/Bar.php` → `tests/Unit/Foo/BarTest.php` (or wherever the suite finder is rooted).

## Assert facade (immediate checks)

Use the `Testo\Assert` facade for in-test checks. Order is **actual, expected** for `same`/`equals`.

```php
Assert::same($user->id, 42);
Assert::notSame($a, $b);
Assert::equals($result, '1');         // loose ==
Assert::true($flag);
Assert::false($flag);
Assert::null($value);
Assert::blank($value);                // null, '', [], or 0-count
Assert::notBlank($value);             // inverse of blank(); false/0/'0' count as non-blank
Assert::contains($collection, $needle);
Assert::count($collection, 3);
Assert::instanceOf($object, MyClass::class);
Assert::fail('explicit failure');
```

Typed chains (use when you want a fluent series of checks on one value):

```php
Assert::string($s)->contains('foo')->notContains('bar');
Assert::int($n)->greaterThan(0)->lessThanOrEqual(100);
Assert::numeric($n)->between(1, 100);  // int, float, or numeric string
Assert::array($a)->hasKeys('id', 'name')->isList()->hasCount(3)->contains('x')->notContains('y');
Assert::array($a)->sameElementsAs([3, 2, 1]);  // order-insensitive, keys ignored
Assert::object($o)->instanceOf(Foo::class)->hasProperty('id');
Assert::json($s)->isObject()->hasKeys(['data', 'meta'])->assertPath('$.data.id', 42);
```

## Expecting exceptions

Use `Testo\Expect` declared **before** the Act phase. The test method's return type is `never`.

```php
use Testo\Expect;

#[Test]
public function rejectsNegativeAmount(): never
{
    Expect::exception(InvalidArgumentException::class)
        ->withMessage('amount must be positive')
        ->withCode(1001);

    new Account(-100);
}
```

Other Expect modifiers: `withMessageContaining(...)`, `withPrevious(class, closure)`, memory-leak expectations.
Do **not** use try/catch-based assertions for expected exceptions — `Expect::exception` is the correct API.

## Marking a test as skipped or cancelled

Throw a status-bearing exception from the test body to short-circuit the run with a non-error verdict:

```php
use Testo\Core\Exception\SkipTest;
use Testo\Core\Exception\CancelTest;

#[Test]
public function requiresPdoMysql(): void
{
    if (!extension_loaded('pdo_mysql')) {
        throw new SkipTest('pdo_mysql required');
    }

    // ... real test ...
}
```

- `SkipTest` → `Status::Skipped`. Use when the test isn't applicable in this environment (missing extension, disabled feature flag, unavailable optional dependency, etc.).
- `CancelTest` → `Status::Cancelled`. Use for cooperative cancellation (deadline expired, Fiber unwind). Not a generic "I don't want to run" — that's `SkipTest`.

Constraints:

- Must escape the **test method itself**. The runner's inner try/catch maps the throw to a status; raising from an interceptor or `#[BeforeTest]`/`#[AfterTest]` hook bubbles out of the pipeline and is treated as `Status::Aborted` instead. To skip from a hook, leave the precondition check inside the test body.
- These are not assertions — don't `try`/`catch` them inside the test, just `throw`.
- Subclasses work: `class MissingExtensionSkip extends SkipTest {}` is still recognized.
- Return type stays `void`, or `never` if the throw is unconditional.

## Tests that intentionally perform no assertions

A test that finishes successfully without recording a single assertion is reported as
`Status::Risky` — the framework assumes you forgot to assert. When a test legitimately verifies
behaviour without the `Assert` facade (e.g. it only checks that a call does **not** throw), declare
that intent with `#[ExpectNoAssertions]` to keep it `Status::Passed`:

```php
use Testo\Assert\ExpectNoAssertions;

#[Test]
#[ExpectNoAssertions]
public function bootsWithoutError(): void
{
    new Kernel()->boot();   // success is simply "no exception thrown"
}
```

Place it on a single test — a method or a function. It is not allowed on a class: "no test here
asserts anything" is rarely a real contract, and a stray class-level marker would flip every
genuinely-asserting test to `Risky`.

The attribute is a **two-way contract**, not just a switch: a marked test that *does* record an
assertion is reported as `Status::Risky` (the declaration is stale or wrong). This includes
`Expect::exception(...)` / `#[ExpectException]` — expecting an exception is itself an assertion, so
pairing it with `#[ExpectNoAssertions]` is contradictory and comes out `Risky`. Use the attribute
only on tests that truly assert nothing.

| `#[ExpectNoAssertions]` | test records an assertion | status |
|---|---|---|
| no | no | `Risky` (forgotten assertion) |
| no | yes | `Passed` |
| yes | no | `Passed` |
| yes | yes | `Risky` (stale/misapplied attribute) |

## Lifecycle hooks

```php
use Testo\Lifecycle\{BeforeClass, AfterClass, BeforeTest, AfterTest};

#[BeforeClass]
public static function bootSchema(): void { /* once before any test */ }
#[BeforeTest]
public function openTx(): void           { /* before each test */ }
#[AfterTest]
public function rollback(): void         { /* after each test */ }
#[AfterClass]
public static function dropSchema(): void { /* once after all tests */ }
```

Hooks may be either instance methods or `static` — Testo invokes them accordingly. They run regardless of `#[Test]` on the method.

In a **function-based test case** (a file of top-level `#[Test]` functions rather than a class), the same
attributes work on plain functions. The hooks apply to that file's case — `#[BeforeClass]`/`#[AfterClass]`
run once around the whole file, `#[BeforeTest]`/`#[AfterTest]` around each test function. A lifecycle
function needs no `#[Test]` and is never itself a test; share state through a `static` holder, since
functions have no `$this`.

```php
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[BeforeTest]
function openTx(): void { Db::$tx = Db::begin(); }   // before each test function in this file

#[Test]
function insertsRow(): void { /* ... */ }
```

## Grouping tests

Label tests with `#[Group]` (from the `testo/filter` plugin) to select or skip them by category.
It targets classes, methods, and functions and is variadic (pass several names at once).

```php
use Testo\Filter\Group;

#[Test]
#[Group('driver-mysql')]     // inherited by every test of the class
final class MysqlConnectionTest
{
    #[Group('slow')]         // effective groups: driver-mysql, slow
    public function importsLargeDataset(): void { /* ... */ }
}
```

A test's group set is the union of all groups reachable from it: its own method (and any overridden
parent method), the test class, its parent classes, and traits. Groups are selected at run time with
`--group` — see the `testo-run-tests` skill.

## Running

Run the test you just wrote through the Testo CLI, always with `--json`:

```
vendor/bin/testo --json --filter='UserServiceTest'
```

Filter selection (`--suite`/`--filter`/`--path`/`--group`/`--type`), the JSON report shape, and exit
semantics are covered by the `testo-run-tests` skill — escalate there before adding other flags.

## Pitfalls

- Do not mock `enum`s or `final` classes — instantiate real ones.
- Do not invent attributes. If you need behaviour you haven't seen in `llms.txt`, escalate to `llms-full.txt` before guessing.
- Do not write `setUp`/`tearDown` — use the lifecycle attributes above.
- For parameterized tests, escalate to the `testo-data-driven` skill.
- For flaky-test handling, escalate to the `testo-flaky-tests` skill.
- For exception assertions, **always** use `Expect::exception(...)` before the throwing call — never wrap in try/catch.
