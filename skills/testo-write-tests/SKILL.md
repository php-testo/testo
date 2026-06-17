---
name: testo-write-tests
description: Write or modify tests in a project that uses the Testo PHP testing framework. Use when adding a #[Test] class, writing assertions with the Assert facade, expecting exceptions with Expect, or adding lifecycle hooks (#[BeforeTest], #[AfterTest], #[BeforeClass], #[AfterClass]). Trigger when the user says "write a test", "add a test for X", "test this class", or edits a file under `tests/`.
---

# Writing tests with Testo

Testo is **not PHPUnit**. The attribute set, assertion facade, exception expectations, and lifecycle
hooks are Testo's own — do not transliterate PHPUnit idioms.

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
Assert::contains($collection, $needle);
Assert::count($collection, 3);
Assert::instanceOf($object, MyClass::class);
Assert::fail('explicit failure');
```

Typed chains (use when you want a fluent series of checks on one value):

```php
Assert::string($s)->contains('foo')->notContains('bar');
Assert::int($n)->greaterThan(0)->lessThanOrEqual(100);
Assert::array($a)->hasKeys(['id', 'name'])->isList()->hasCount(3)->contains('x')->notContains('y');
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

## Lifecycle hooks

```php
use Testo\Lifecycle\{BeforeClass, AfterClass, BeforeTest, AfterTest};

#[BeforeClass] public static function bootSchema(): void { /* once before any test */ }
#[BeforeTest]  public function openTx(): void           { /* before each test */ }
#[AfterTest]   public function rollback(): void         { /* after each test */ }
#[AfterClass]  public static function dropSchema(): void { /* once after all tests */ }
```

Hooks may be either instance methods or `static` — Testo invokes them accordingly. They run regardless of `#[Test]` on the method.

## Grouping tests

Label tests with `#[Group]` (from the `testo/filter` plugin) to select or skip them by category.
It targets classes, methods, and functions and is variadic (pass several names at once).

```php
use Testo\Filter\Group;

#[Test]
#[Group('integration')]      // inherited by every test of the class
final class OrderTest
{
    #[Group('slow')]         // effective groups: integration, slow
    public function importsLargeDataset(): void { /* ... */ }
}
```

A test's group set is the union of all groups reachable from it: its own method (and any overridden
parent method), the test class, its parent classes, and traits. Select with `--group` (OR across
values); prefix with `!` to exclude. Group filters AND with name/path/suite filters.

## Running

```
vendor/bin/testo                                # all suites
vendor/bin/testo --suite=Unit                   # one suite
vendor/bin/testo --filter='UserServiceTest'     # by name
vendor/bin/testo --path=tests/Unit/UserService  # by path
vendor/bin/testo --group=integration            # only the "integration" group
vendor/bin/testo --group=!slow                  # everything except the "slow" group
```

Use the Testo CLI, **never** `phpunit`.

## Pitfalls

- Do not mock `enum`s or `final` classes — instantiate real ones.
- Do not invent attributes. If you need behaviour you haven't seen in `llms.txt`, escalate to `llms-full.txt` before guessing.
- Do not write `setUp`/`tearDown` — use the lifecycle attributes above.
- For parameterized tests, escalate to the `testo-data-driven` skill.
- For flaky-test handling, escalate to the `testo-flaky-tests` skill.
- For exception assertions, **always** use `Expect::exception(...)` before the throwing call — never wrap in try/catch.
