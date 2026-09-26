# PHPUnit → Testo mapping (authoritative)

The single source of truth for *what each PHPUnit construct becomes in Testo*. Both migration
paths use it: the **Rector** path automates the mechanical rows; the **AI-agent** path ports every
row by hand. When in doubt about an attribute, read its class in the installed `vendor/testo/`.

Testo is similar in spirit to PHPUnit but **not source-compatible**. Never run a blind regex pass —
the assertion **argument order flips** (see the pitfalls), and discovery is attribute-based.

## Which rows Rector handles

| Handled automatically by the `phpunit-to-testo` Rector set | Needs AI/human work (no faithful rule) |
|---|---|
| `extends TestCase` removed + `#[Test]` on test methods (also in subclasses of a project base), assert calls (+ arg-order swap), `expectException` with its message (`withMessageContaining`), regex message (`withMessagePattern`) and code, `markTestSkipped`, `setUp`/`tearDown` → attributes, `@dataProvider`/`#[DataProvider]`, `@group`/`#[Group]`, `#[CoversClass]` → `#[Covers]`, `#[DoesNotPerformAssertions]`, with a mock set added: `createMock`/`createStub` (+ intersection, `getMockBuilder(…)->disableOriginalConstructor()->getMock()`) + `expects`/`method`/`will*`/`with` constraints → Double (`phpunit-to-double`) or Mockery (`phpunit-to-mockery`) | tests inherited from a PHPUnit base class in `vendor/`, the mock forms with no Double/Mockery target (`prophesize`, `getMockForAbstractClass`, `addMethods`, `withConsecutive`, a variable invocation matcher), `assertThat` constraints |

> The left column is mechanical; the right column is why **every** migration ends with an AI/human
> pass and a test-count check against the PHPUnit run.

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
| `$this->assertGreaterThan($e, $a)` (+`GreaterThanOrEqual`/`LessThan`/`LessThanOrEqual`) | `Assert::numeric($a)->greaterThan($e)` — subject first, threshold in the matcher. |
| `$this->assertArrayHasKey($k, $a)` / `assertArrayNotHasKey` | `Assert::array($a)->hasKeys($k)` / `->doesNotHaveKeys($k)`. Variadic — no `$message` arg. |
| `$this->assertEqualsCanonicalizing($e, $a)` | `Assert::array($a)->sameElementsAs($e)` — order-insensitive, loose comparison. |
| `$this->assertEmpty($a)` / `assertNotEmpty($a)` | `Assert::blank($a)` / `Assert::notBlank($a)` **only when `$a` is an array** — `blank()` treats `false`/`0`/`'0'` as valid data, so for other types port by hand. |
| `$this->assertMatchesRegularExpression($p, $s)` / `assertDoesNotMatchRegularExpression` | `Assert::string($s)->matchesPattern($p)` / `->notMatchesPattern($p)` — subject first, same full PCRE pattern. |
| `$this->assertNotTrue($x)` / `assertNotFalse($x)` | `Assert::notSame($x, true)` / `Assert::notSame($x, false)`. |
| `$this->assertStringContainsString($n, $s)` / `assertStringNotContainsString` | `Assert::string($s)->contains($n)` / `->notContains($n)`. |
| `$this->assertNotContains($n, $h)` | `Assert::iterable($h)->notContains($n)` — strict `===`, like `Assert::contains()`. |
| `$this->assertObjectHasProperty($p, $o)` / `assertObjectNotHasProperty` | `Assert::object($o)->hasProperty($p)` / `Assert::false(\property_exists($o, $p))`. |
| `$this->assertIsList($a)` | `Assert::array($a)->isList()`. |
| `$this->assertContainsOnlyInt($h)` (+`Array`/`Bool`/`Float`/`Null`/`String`) | `Assert::iterable($h)->allOf('int')`. `allOf()` matches the exact `get_debug_type()`, so `assertContainsOnlyInstancesOf(Foo::class, $h)` → `allOf(Foo::class)` only for a `final` Foo; `Object`/`Scalar`/`Numeric`/… have no matcher. |
| `$this->assertSameSize($e, $a)` | `Assert::iterable($a)->sameSizeAs($e)` for arrays and `Countable` iterables. |
| `$this->assertNotInstanceOf(Foo::class, $x)` | `Assert::false($x instanceof Foo)`. |
| `$this->assertFinite($x)` / `assertInfinite` / `assertNan` | `Assert::true(\is_finite($x))` and so on, when `$x` is an `int` or `float` — PHPUnit fails any other type. |
| `$this->assertFileIsReadable($f)` / `assertDirectoryIsWritable($d)` and the other permission checks | `Assert::true(\is_readable($f))`; a negated or directory check needs the existence part too: `Assert::true(\is_dir($d) && \is_writable($d))`. |
| `$this->assertIsResource($x)` / `assertIsClosedResource($x)` | `Assert::true(\str_starts_with(\gettype($x), 'resource'))` / `Assert::same(\gettype($x), 'resource (closed)')` — PHPUnit counts a closed resource, `is_resource()` does not. |
| `$this->assertJson($s)` | `Assert::json($s)` (no `$message` arg). The JSON equality assertions have no counterpart: PHPUnit compares canonical re-encoded JSON (`1` ≠ `1.0`, `{}` ≠ `[]`), which `Assert::equals()` over decoded values does not. |
| `$this->expectException(X::class)` before Act | `Expect::exception(X::class)->withCode(...)` before Act. Method return type becomes `never`. |
| `$this->expectExceptionMessage('...')` | `->withMessageContaining('...')` — PHPUnit matches a substring; `withMessage()` is an exact match and fails where PHPUnit passed. |
| `$this->expectExceptionMessageMatches('/.../')` | `->withMessagePattern('/.../')`. |
| `$this->markTestSkipped('reason')` | `#[Skip('reason')]` from **`Testo\Skip`** when the call opens the test unconditionally — the test then never enters the pipeline (no hooks, no provider, no retries). A guarded call, one deeper in the body, or a non-literal message stays runtime: `throw new \Testo\Core\Exception\SkipTest('reason')` from the test body. |
| `$this->markTestIncomplete('reason')` | No "incomplete" status. Port to `throw new SkipTest('TODO: reason')`, or leave the body empty → `Status::Risky`. |
| `#[DoesNotPerformAssertions]` / `$this->expectNotToPerformAssertions()` | `#[ExpectNoAssertions]` from **`Testo\Assert`**, on a method or function (not a class) — no method-call form. Two-way contract: a marked test that *does* assert is `Status::Risky`. |
| `$this->createMock(Foo::class)` | Testo core ships no mocking; the doubling library is `testo/bridge-double`. `$this->createMock`/`createStub` → `\JMac\Testing\Double::for(Foo::class)` and the `expects()->method()->willReturn()` chain → `expects('m')->times(1)->returns(...)`, with `with()` constraints mapped onto `Argument::*` (`anything`→`any`, `isInstanceOf`→`type`, `callback`→`satisfies`, …). The `phpunit-to-double` Rector set does all this automatically — also `willReturnSelf`/`willReturnMap`, `createConfiguredMock`, partial mocks (`createPartialMock`, `onlyMethods`) as `passthru()` doubles, and every constraint without a dedicated matcher as an `Argument::satisfies()` predicate that reproduces PHPUnit's verdict; only `prophesize`, `getMockForAbstractClass`, `addMethods`, `withConsecutive` and a variable invocation matcher stay manual. Prefer Mockery (or PHP 8.2)? The `phpunit-to-mockery` set converts the same chains to `\Mockery::mock(Foo::class)->shouldIgnoreMissing()` + `shouldReceive()`; add `testo/bridge-mockery` — like the Double bridge, it verifies and isolates mocks after every test (drops the `tearDown()` / `MockeryPHPUnitIntegration` boilerplate) and counts a fulfilled expectation as an assertion, so a mock-only test stays out of `Status::Risky`. A suite already on Mockery moves to Double with the `mockery-to-double` set. **Never** mock `final` classes or enums. |
| `assertThat($v, $constraint)` | No constraint objects. Decompose into concrete `Assert::*` calls. |
| `@group slow` / `#[Group('slow')]` | `#[Group('slow')]` from **`Testo\Filter\Group`**. Not repeatable — merge: `#[Group('slow','db')]`. Class-level groups are inherited (union with the method's). Select `--group=slow`, exclude `--group=!slow`. |
| `#[Repeat($times, $threshold)]` (PHPUnit 13.3+) | `#[\Testo\Repeat(times: $times, maxFailures: $threshold - 1)]` — `failureThreshold` (aborting count) → `maxFailures` (tolerated count), off by one; default `1` → omitted. |
| `#[Retry($maxAttempts)]` (PHPUnit 13.3+) | `#[\Testo\Retry(maxAttempts: $maxAttempts)]`. |
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
- **Tests inherited from a `vendor/` PHPUnit base** (e.g. a shared `AbstractCollectorTestCase`) cannot be ported in place: the base stays PHPUnit and its assertions are invisible to Testo. Copy the base into a local trait or abstract class under the test tree (e.g. `tests/Support/`), run the Rector pass over it with the rest of the scope, and point the subclasses at the copy. A base that boots an application in `setUp()` usually cleans it up in `tearDown()`: carry that over as an `#[AfterTest]` method, not only the `#[BeforeTest]` part.
- **A trait method alias is a second test.** `use T { testFoo as private baseTestFoo; }` copies `#[Test]` and `#[DataProvider]` onto the alias. Move the shared body into a private helper without attributes and call it from the overriding test.
- **Assertion counts differ from PHPUnit** (`Expect::exception()`, a chained `Assert::array()->hasKeys()->…` count differently). Verify the port by the test count, not the assertion count.

For choosing between `#[DataSet]`, `#[DataProvider]`, `#[DataZip]`, `#[DataUnion]`, `#[DataCross]`, see `testo-data-driven`. For lifecycle/assertion detail, see `testo-write-tests`.
