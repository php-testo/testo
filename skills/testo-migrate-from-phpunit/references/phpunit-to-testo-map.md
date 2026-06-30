# PHPUnit → Testo mapping (authoritative)

The single source of truth for *what each PHPUnit construct becomes in Testo*. Both migration
paths use it: the **Rector** path automates the mechanical rows; the **AI-agent** path ports every
row by hand. When in doubt about an attribute, fetch `https://php-testo.github.io/llms.txt`.

Testo is similar in spirit to PHPUnit but **not source-compatible**. Never run a blind regex pass —
the assertion **argument order flips** (see the pitfalls), and discovery is attribute-based.

## Which rows Rector handles

| Handled automatically by the `phpunit-to-testo` Rector set | Needs AI/human work (no faithful rule) |
|---|---|
| assert calls (+ arg-order swap), bare `expectException`, `markTestSkipped`, `setUp`/`tearDown` → attributes, `@dataProvider`/`#[DataProvider]`, `@group`/`#[Group]`, `#[CoversClass]` → `#[Covers]`, `#[DoesNotPerformAssertions]` | **remove `extends TestCase` + reconcile discovery**, mocks, `assertThat` constraints, `expectExceptionMessageMatches` (regex), `markTestIncomplete`, fluent exception message/code folding |

> The left column is mechanical; the right column is why **every** migration ends with an AI/human
> pass — Rector alone leaves the test class still extending `TestCase`, so Testo will not discover it.

## Translation table

| PHPUnit | Testo |
|---|---|
| `extends TestCase` | *remove the base class* — Testo doesn't require one. |
| `/** @test */` or `function testFoo()` | `#[Test]` on class (preferred) or method. Drop the `test` prefix. |
| `@covers App\Foo` / `#[CoversClass(Foo::class)]` | `#[Covers(Foo::class)]` (class-level if uniform; method-level if mixed). |
| `@coversNothing` / `#[CoversNothing]` | `#[CoversNothing]`. |
| `setUp()` / `tearDown()` | `#[BeforeTest]` / `#[AfterTest]` on any-named method. |
| `setUpBeforeClass()` / `tearDownAfterClass()` | `#[BeforeClass]` / `#[AfterClass]` (static). |
| `@dataProvider source` / `#[DataProvider('source')]` | `#[DataProvider('source')]`. Provider must be `public static` returning `iterable`. Replace numeric keys with `'label' => [...]` yields. |
| `@testWith [[…],[…]]` / `#[TestWith([…])]` | One `#[DataSet([…], 'label')]` per row — `#[DataSet]` is repeatable, so stack them. |
| `#[TestWithJson('[…]')]` | Decode the JSON yourself and pass as `#[DataSet([...])]`. Testo ships no JSON-source attribute. |
| `$this->assertSame($expected, $actual)` | `Assert::same($actual, $expected)` — **argument order is `actual, expected`**. |
| `$this->assertEquals(...)` | `Assert::equals($actual, $expected)` (loose ==). Prefer `Assert::same` unless loose is intentional. |
| `$this->assertTrue/False/Null` | `Assert::true/false/null`. |
| `$this->assertCount(3, $coll)` | `Assert::count($coll, 3)` — count goes second. |
| `$this->assertContains($needle, $hay)` | `Assert::contains($hay, $needle)` — haystack first. |
| `$this->assertInstanceOf(Foo::class, $o)` | `Assert::instanceOf($o, Foo::class)`. |
| `$this->expectException(X::class)` before Act | `Expect::exception(X::class)->withMessage(...)->withCode(...)` before Act. Method return type becomes `never`. |
| `$this->expectExceptionMessageMatches('/.../')` | `withMessageContaining('substring')` if a literal substring suffices; otherwise catch and assert manually. No PCRE. |
| `$this->markTestSkipped('reason')` | `throw new \Testo\Core\Exception\SkipTest('reason')` from the test body. |
| `$this->markTestIncomplete('reason')` | No "incomplete" status. Port to `throw new SkipTest('TODO: reason')`, or leave the body empty → `Status::Risky`. |
| `#[DoesNotPerformAssertions]` / `$this->expectNotToPerformAssertions()` | `#[ExpectNoAssertions]` from **`Testo\Assert`**, on class/method/function — no method-call form. Two-way contract: a marked test that *does* assert is `Status::Risky`. |
| `$this->createMock(Foo::class)` | Testo ships no mocking. Bring your own (Mockery, Prophecy) or — preferred — a hand-rolled fake. **Never** mock `final` classes or enums. |
| `assertThat($v, $constraint)` | No constraint objects. Decompose into concrete `Assert::*` calls. |
| `@group slow` / `#[Group('slow')]` | `#[Group('slow')]` from **`Testo\Filter\Group`**. Not repeatable — merge: `#[Group('slow','db')]`. Class-level groups are inherited (union with the method's). Select `--group=slow`, exclude `--group=!slow`. |
| `@requires ext` | Suite separation in `testo.php` via `SuiteConfig` + finder excludes. |
| `phpunit.xml` | `testo.php` (a real PHP file returning `ApplicationConfig`). Generate it with `vendor/bin/testo init` (scans `tests/` for suite folders); hand-tune per `testo-configure`. |

## Worked example

**Before (PHPUnit):**
```php
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\UserService
 * @group user
 */
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

    /**
     * @dataProvider invalidNames
     * @group validation
     */
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
use Testo\Filter\Group;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(UserService::class)]
#[Group('user')]
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
    #[Group('validation')]
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

## Pitfalls (these break tests silently)

- **Argument order flip.** `assertSame($expected, $actual)` (PHPUnit) → `Assert::same($actual, $expected)` (Testo). A blind regex inverts every comparison. (Rector's rule swaps correctly; a hand port must remember.)
- **`expectException` must come *before* the Act.** PHPUnit allowed both; Testo's `Expect::exception` is "declare → trigger".
- **Don't keep `extends TestCase` "just in case".** A leftover base class drags in PHPUnit and the class won't be discovered as a Testo test.
- **Class-level `#[Test]` excludes non-test methods by signature.** Data providers (`public static`, return `iterable`) and helpers are not mistaken for tests, but a public `void` helper *will* be — make helpers `private` or non-`void`.
- **Don't mock `final` classes or enums.** Instantiate the real type or extract an interface.
- **Don't run both runners in CI during migration.** Cut over one suite/dir at a time.

For choosing between `#[DataSet]`, `#[DataProvider]`, `#[DataZip]`, `#[DataUnion]`, `#[DataCross]`, see `testo-data-driven`. For lifecycle/assertion detail, see `testo-write-tests`.
