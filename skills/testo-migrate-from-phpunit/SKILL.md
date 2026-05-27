---
name: testo-migrate-from-phpunit
description: Migrate an existing PHPUnit test suite to Testo. Use when the user says "migrate from PHPUnit", "port phpunit tests", "convert TestCase to Testo", or has files extending PHPUnit\Framework\TestCase that need to move to Testo's attribute-based style.
---

# Migrating PHPUnit → Testo

Testo's surface is intentionally similar in spirit but **not source-compatible** with PHPUnit.
Don't run a regex pass — port file-by-file with the table below, then run `vendor/bin/testo --suite=<name>`
after each batch.

Fetch `https://php-testo.github.io/llms.txt` first — when in doubt about an attribute, that's the source of truth.

## Translation table

| PHPUnit | Testo |
|---|---|
| `extends TestCase` | *remove the base class* — Testo doesn't require one. |
| `/** @test */` or `function testFoo()` | `#[Test]` on class (preferred) or method. Drop the `test` prefix. |
| `@covers App\Foo` | `#[Covers(Foo::class)]` (class-level if uniform; method-level if mixed). |
| `@coversNothing` | `#[CoversNothing]`. |
| `setUp()` / `tearDown()` | `#[BeforeTest]` / `#[AfterTest]` on any-named method. |
| `setUpBeforeClass()` / `tearDownAfterClass()` | `#[BeforeClass]` / `#[AfterClass]` (static). |
| `@dataProvider source` / `#[DataProvider('source')]` (PHPUnit 10+) | `#[DataProvider('source')]`. Provider must be `public static` returning `iterable`. Replace numeric keys with `'label' => [...]` yields. |
| `@testWith [[…], […]]` / `#[TestWith([…])]` (PHPUnit 10+) | One `#[DataSet([…], 'label')]` per row — `#[DataSet]` is repeatable, so stack them on the method. |
| `#[TestWithJson('[…]')]` | Decode the JSON yourself and pass as a `#[DataSet([...])]`, or move to `#[DataProvider]` if the data is large. Testo doesn't ship a JSON-source attribute. |
| `$this->assertSame($expected, $actual)` | `Assert::same($actual, $expected)` — **argument order is `actual, expected`** in Testo. |
| `$this->assertEquals(...)` | `Assert::equals($actual, $expected)` (loose ==). Prefer `Assert::same` unless loose comparison is intentional. |
| `$this->assertTrue/False/Null` | `Assert::true/false/null`. |
| `$this->assertCount(3, $coll)` | `Assert::count($coll, 3)` — count goes second. |
| `$this->assertContains($needle, $hay)` | `Assert::contains($hay, $needle)` — haystack first. |
| `$this->assertInstanceOf(Foo::class, $o)` | `Assert::instanceOf($o, Foo::class)`. |
| `$this->expectException(X::class)` etc. before Act | `Expect::exception(X::class)->withMessage(...)->withCode(...)` before Act. Method return type becomes `never`. |
| `$this->expectExceptionMessageMatches('/.../')` | Use `withMessageContaining('substring')` or escalate to `llms-full.txt` for regex support. |
| `$this->markTestSkipped('reason')` | `throw new \Testo\Core\Exception\SkipTest('reason')` from inside the test body. See the "Marking a test as skipped or cancelled" section in `testo-write-tests`. |
| `$this->markTestIncomplete('reason')` | Testo has no distinct "incomplete" status. Port to `throw new SkipTest('TODO: reason')`, or leave the test empty so it's reported as `Status::Risky`. |
| Mocks: `$this->createMock(Foo::class)` | Testo doesn't ship a mocking library. Bring your own (Mockery, Prophecy), or — preferred — write a hand-rolled fake. **Never** mock `final` classes or enums. |
| `@group slow`, `@requires ext` | Suite separation in `testo.php` via `SuiteConfig` and finder excludes. |
| `phpunit.xml` | `testo.php` (a real PHP file returning `ApplicationConfig`). See `testo-configure` skill. |

## Worked example

**Before (PHPUnit):**
```php
use PHPUnit\Framework\TestCase;

/** @covers \App\UserService */
final class UserServiceTest extends TestCase
{
    private UserService $svc;

    protected function setUp(): void
    {
        $this->svc = new UserService(new InMemoryRepo());
    }

    public function testCreatesUser(): void
    {
        $u = $this->svc->create('Alice');
        $this->assertSame('Alice', $u->name);
    }

    /** @dataProvider invalidNames */
    public function testRejectsInvalidName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid name');
        $this->svc->create($name);
    }

    public static function invalidNames(): array
    {
        return [[''], ['  '], [str_repeat('a', 256)]];
    }
}
```

**After (Testo):**
```php
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(UserService::class)]
final class UserServiceTest
{
    private UserService $svc;

    #[BeforeTest]
    public function init(): void
    {
        $this->svc = new UserService(new InMemoryRepo());
    }

    public function createsUser(): void
    {
        $u = $this->svc->create('Alice');

        Assert::same($u->name, 'Alice');
    }

    #[DataProvider('invalidNames')]
    public function rejectsInvalidName(string $name): never
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('invalid name');

        $this->svc->create($name);
    }

    public static function invalidNames(): iterable
    {
        yield 'empty'      => [''];
        yield 'whitespace' => ['  '];
        yield 'too long'   => [str_repeat('a', 256)];
    }
}
```

## `#[TestWith]` / `@testWith` → `#[DataSet]`

PHPUnit's `#[TestWith]` (and the older `@testWith` annotation) puts each row of test data directly on the
method as a separate attribute. Testo's equivalent is **`#[DataSet]`**, also repeatable.

**Before (PHPUnit 10+):**
```php
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgeValidatorTest extends TestCase
{
    #[Test]
    #[TestWith([12, false], 'below minimum')]
    #[TestWith([18, true],  'within range')]
    #[TestWith([65, false], 'above maximum')]
    public function isValid(int $age, bool $expected): void
    {
        $this->assertSame($expected, AgeValidator::isValid($age));
    }
}
```

**After (Testo):**
```php
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
final class AgeValidatorTest
{
    #[DataSet([12, false], 'below minimum')]
    #[DataSet([18, true],  'within range')]
    #[DataSet([65, false], 'above maximum')]
    public function isValid(int $age, bool $expected): void
    {
        Assert::same(AgeValidator::isValid($age), $expected);
    }
}
```

Mapping rules:

- One `#[TestWith]` → one `#[DataSet]`. Both attributes are `IS_REPEATABLE`, so the row count is preserved 1:1.
- First argument: positional array of method arguments. **The shape is identical** between PHPUnit and Testo, so you can move the bracketed payload verbatim.
- Second argument: the human-readable case name. In PHPUnit it's an optional second arg of `#[TestWith]`; in Testo it's the second arg of `#[DataSet]` (also optional, but **strongly recommend keeping it** — failure output uses it).
- If the PHPUnit code used `#[TestWithJson('[…]')]`, decode the JSON to PHP at port time and write a `#[DataSet([...])]`. Testo doesn't ship a JSON-source attribute by design.
- Note that during the port, the assertion order **flips** (`$expected, $actual` → `$actual, $expected`). Easy to miss when you're only changing the attributes.

For more on choosing between `#[DataSet]`, `#[DataProvider]`, `#[DataZip]`, `#[DataUnion]` and `#[DataCross]`, escalate to the `testo-data-driven` skill.

## Recommended porting order

1. Convert `phpunit.xml` → minimal `testo.php` with one `SuiteConfig` per test directory. (See `testo-configure` skill.)
2. Port leaf utility tests first (pure functions, no mocks). Run after each file.
3. Port tests that use fakes/stubs you already control.
4. Port tests that use `createMock` / Prophecy last — these need design decisions: hand-rolled fake, or keep the mock library as a dependency.
5. Migrate `@dataProvider` to `#[DataProvider]` and `#[TestWith]` / `@testWith` to `#[DataSet]` once the test class is otherwise green.
6. Delete `phpunit.xml`, `phpunit/phpunit` from `composer.json`, and any `tests/bootstrap.php` only after every test runs under `vendor/bin/testo`.

## Pitfalls

- **Argument order flip.** `assertSame($expected, $actual)` (PHPUnit) → `Assert::same($actual, $expected)` (Testo). A blind regex will silently invert your test outputs.
- **Don't mock `final` classes or enums.** Testo doesn't ship a workaround for these — instantiate the real type.
- **`expectException` must come *before* the Act phase.** PHPUnit allowed both orderings; Testo's `Expect::exception` requires "declare → trigger".
- **Don't keep `extends TestCase` "just in case".** A leftover base class drags in PHPUnit and confuses both runners.
- **Don't run both runners in CI during migration.** Pick a cutover point per directory and switch one suite at a time.
