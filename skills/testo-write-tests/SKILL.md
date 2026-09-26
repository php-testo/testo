---
name: testo-write-tests
description: 'Write or modify tests in a project that uses the Testo PHP testing framework. Use when adding a #[Test] class, writing assertions with the Assert facade, expecting exceptions with Expect, or adding lifecycle hooks (#[BeforeTest], #[AfterTest], #[BeforeClass], #[AfterClass]). Trigger when the user says "write a test", "add a test for X", "test this class", or edits a file under `tests/`.'
---

# Writing tests with Testo

The attribute set, assertion facade, exception expectations, and lifecycle hooks are Testo's own.
Write them the Testo way described below — don't transliterate idioms from other test frameworks.

## Before you write code

This skill is the API surface for ordinary tests; sibling `testo-*` skills cover data providers, doubles,
async, coverage and the rest. When a name here disagrees with the installed version, `vendor/testo/`
wins — verify against it before relying on memory.

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

        Assert::same($user->name, 'Alice');
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
Assert::bool($flag);                  // type check, no chain: only true/false pass, 0/1/'true'/null fail
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
Assert::string($s)->contains('foo')->notContains('bar')->startsWith('f')->endsWith('.txt')->notStartsWith('b')->notEndsWith('.md');
Assert::string($date)->matchesRegex('/^\d{4}-\d{2}-\d{2}$/')->notMatchesRegex('/\s$/');  // full PCRE; invalid pattern → InvalidArgumentException
Assert::string($out)->ignoringLineEndings()->same("a\nb\n");        // whole-string ===, never numeric; notSame() too
Assert::string($html)->ignoringCase()->contains('<title>');          // mb_strtolower() of string and argument
Assert::string($out)->ignoringLineEndings()->endsWith("done\n");     // \r\n and \r count as \n on both sides
Assert::string($html)->ignoringWhitespace()->contains("<li>\n<b>Total:</b> 5\n</li>");  // per line: trim, collapse spaces
Assert::string($sql)->ignoringWhitespace(lineBreaks: true)->startsWith('SELECT id, name FROM');  // one trimmed line
Assert::string($out)->ignoringBlankLines()->contains("Step 1\nStep 2");  // empty and whitespace-only lines removed
Assert::string($console)->ignoringAnsi()->contains('[OK] Cache cleared');  // colors, cursor codes, OSC 8 links stripped
Assert::int($n)->greaterThan(0)->lessThanOrEqual(100);
Assert::numeric($n)->between(1, 100);  // int, float, or numeric string
Assert::array($a)->hasKeys('id', 'name')->isList()->hasCount(3)->contains('x')->notContains('y');
Assert::array($a)->sameElementsAs([3, 2, 1]);  // order-insensitive, keys ignored
Assert::iterable($ids)->allOf('int');          // exact get_debug_type() of every element
Assert::iterable($users)->allInstanceOf(User::class);  // instanceof: subclasses and implementations pass
Assert::object($o)->instanceOf(Foo::class)->hasProperty('id');
Assert::callable($handler);   // is_callable() in the assertion's scope: private/protected methods fail
Assert::callable($factory)->isStatic()->hasReturnType('?Foo');  // notStatic() too
Assert::json($s)->isObject()->hasKeys(['data', 'meta'])->assertPath('$.data.id', 42);
```

The string modifiers (`ignoringCase()`, `ignoringLineEndings()`, `ignoringWhitespace()`, `ignoringBlankLines()`, `ignoringAnsi()`) return a new chain and apply to every check after them; the chain they were called on stays strict, and there is no way to switch a mode off. They normalize the string and each check argument by the same rules, in a fixed order whatever the call order (ANSI, line endings, whitespace, blank lines, case). A substring, prefix or suffix that becomes empty after normalization, like `contains('  ')` under `ignoringWhitespace()`, throws `InvalidArgumentException`; `same('')` stays a valid check. On failure `same()` diffs the normalized strings. Since arguments are trimmed, `contains(' foo ')` no longer checks word boundaries: use `matchesRegex('/\bfoo\b/')`. Regex checks run on the string normalized by every modifier except `ignoringCase()` (use the `i` flag); the pattern itself is never changed.

The callable checks reflect what the callable points to: the closure, the function, the method of a `[$obj, 'm']` / `[Foo::class, 'm']` array or `'Foo::m'` string, or `__invoke()`. `isStatic()` passes for a closure declared `static`, a static method and a plain function (`'strlen'`, `strlen(...)`); an instance method, an invokable object and a closure not declared `static` fail it. `hasReturnType()` compares the declared type as written, ignoring member order, case, leading backslashes and whitespace, with `?T` equal to `T|null`; it does not resolve subtypes or `self`, and a callable without a declared type fails.

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

Other Expect modifiers: `withMessageContaining(...)`, `withMessageMatchingRegex(...)` (`withMessagePattern()` is its deprecated alias; the `testo-shift` Rector set of `testo/bridge-rector` renames it), `withPrevious(class, closure)`, memory-leak expectations.
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

## Skipping a test with #[Skip]

To skip a test declaratively — without running any of its code — put `Testo\Skip` (from the
`testo/skip` plugin) on the test method (inherited by an overriding
method that does not repeat it), the class (skips every test of the case; inherited from parents
and traits, a method-level reason wins), or a free function:

```php
use Testo\Skip;

#[Test]
#[Skip('broken by the pricing rework, see ISSUE-123')]
public function calculatesTotal(): void { /* ... */ }   // reported as Skipped, body never runs
```

The test is reported as `Status::Skipped` and counted in the totals; its reason travels in the
result's failure message `{testId} is skipped via #[Skip] ==> {reason}` (without ` ==> ...` when
the reason is empty). The JUnit, TeamCity and HTML reports show that message; the terminal prints
the skipped line without it, and the compact `--json` report only counts the test in
`totals.skipped`.

`reason` is optional and the attribute is not repeatable — but **always pass a reason that points
at an issue** (`#[Skip('flaky on CI, see ISSUE-123')]`); a bare `#[Skip]` is how a skipped test rots
unreviewed. The attribute needs no plugin registration: it wires its own interceptor, from a class,
a method or a function alike.

Which skipping tool to reach for:

| Tool | Decided by | Visibility | Use when |
|---|---|---|---|
| `#[Skip('...')]` | code, ahead of time | always reported; reason in JUnit/TeamCity/HTML | the test is knowingly broken, tracked in an issue, and must be returned to |
| `throw SkipTest` | test body, at runtime | reported when the run gets there | test isn't applicable in this environment |
| `#[Group]` + `--group=!x` | runner invocation | invisible — filtered out of reports | a category you sometimes don't run |

Runtime contract of `#[Skip]`: the test is reported at the entry of its pipeline, so
`#[BeforeTest]`/`#[AfterTest]`, data providers, `#[Retry]`/`#[Repeat]`, fibers and coverage never
engage, and a data-driven test yields a single Skipped entry (the provider is not called).
`#[BeforeClass]`/`#[AfterClass]` run when the case still has a test to run; when every test of the
case is skipped they stay silent and the case class is never constructed (enabled neighbors
construct it as usual). A run of only `#[Skip]`-marked tests is a success (exit 0). `#[Skip]`
applies to plain tests only: on a `#[Bench]` or `#[TestInline]` target it is inert — the benchmark
or inline case runs as usual. The `testo/skip` plugin (`Testo\Skip\SkipPlugin`) is part of the
default suite plugins; it is what tells the lifecycle hooks about the skip ahead of the run.

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
    (new Kernel())->boot();   // success is simply "no exception thrown"
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

- Do not mock `enum`s or `final` classes — instantiate real ones. For stubs, spies, mocks and fakes (Double, Mockery, hand-written), escalate to the `testo-test-doubles` skill.
- Do not invent attributes. If you need behaviour no `testo-*` skill describes, look for it in the installed `vendor/testo/` before guessing.
- Do not write `setUp`/`tearDown` — use the lifecycle attributes above.
- For parameterized tests, escalate to the `testo-data-driven` skill.
- For flaky-test handling, escalate to the `testo-flaky-tests` skill.
- For fiber/coroutine or async I/O tests (`\Fiber::suspend()`, amphp, Revolt, `Future::await()`), escalate to the `testo-async` skill.
- For exception assertions, **always** use `Expect::exception(...)` before the throwing call — never wrap in try/catch.
