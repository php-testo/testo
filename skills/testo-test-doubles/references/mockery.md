# Doubles with Mockery (`testo/bridge-mockery`)

Reference for the Mockery route of `testo-test-doubles`. [Mockery](https://docs.mockery.io) is the
established PHP mocking library; the bridge calls `\Mockery::close()` in a `finally` after every test, so
expectations are verified and the container is reset without teardown code.

## 1. Pre-flight

Read the `MOCKERY` block of `scripts/precheck.php` (run from `SKILL.md` Step 2):

| Row | Meaning when `NO` |
|---|---|
| `mockery/mockery` | Library missing → §2 |
| `testo/bridge-mockery` | Bridge missing → §2. Without it nothing calls `Mockery::close()`, so expectations never verify. |
| `MockeryPlugin registered` | `testo.php` does not mention `MockeryPlugin` → §2.2. Same effect: silent non-verification, mock-only tests `Risky`. |

Mockery runs on Testo's PHP floor (8.2).

## 2. Install

### 2.1 Packages

```bash
composer require --dev testo/bridge-mockery
```

The bridge pulls `mockery/mockery` in. Do **not** add `mockery/mockery`'s PHPUnit integration trait or a
`tearDown()` — Testo has neither.

### 2.2 Register the plugin

Application-wide:

```php
// testo.php
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Mockery\MockeryPlugin;

return new ApplicationConfig(
    plugins: [new MockeryPlugin()],
    suites:  [new SuiteConfig(name: 'Unit', location: ['tests/Unit'])],
);
```

Per suite: `plugins: SuitePlugins::with(new MockeryPlugin())` on the `SuiteConfig`
(`Testo\Application\Config\Plugin\SuitePlugins`).

Once registered, every test ends with `\Mockery::close()`: unmet expectations fail the test
(`Status::Failed`), verified ones are recorded as one fulfilled assertion, the container is cleared.
Re-run the pre-flight; the `MOCKERY` verdict must read `READY`.

## 3. Usage

Type the variable as the intersection of Mockery's interface and the real contract:

```php
use Mockery\MockInterface;

/** @var MockInterface&BookRepository $repo */
$repo = \Mockery::mock(BookRepository::class);
```

Mockery 1.6 has two expectation verbs mirroring Double's: **`allows('m')`** (stub, any count) and
**`expects('m')`** (mock, once by default). `shouldReceive('m')` is the older spelling of `allows()` that
needs an explicit count to become a mock. Prefer `allows`/`expects` in new code.

### Dummy

```php
$logger = \Mockery::spy(LoggerInterface::class);    // spy: every call returns null, nothing to configure
$service = new Checkout($gateway, $logger);
```

A plain `\Mockery::mock()` throws on any unconfigured call, so for a pure dummy use `spy()` or
`mock()->shouldIgnoreMissing()`.

### Stub

```php
/** @var MockInterface&BookRepository $repo */
$repo = \Mockery::mock(BookRepository::class);
$repo->allows('find')->with(123)->andReturn($book);
$repo->allows('find')->with(999)->andThrow(new NotFound());
$repo->allows('next')->andReturn($first, $second);        // consecutive; last value repeats
$repo->allows('price')->andReturnUsing(fn(int $id) => $id * 10);
$repo->allows('self')->andReturnSelf();
$repo->allows('echo')->andReturnArg(0);

$service = new Catalog($repo);

Assert::same($service->title(123), 'Dune');
```

Argument matching for `with()` — literals compare loosely (`==`), objects by identity, or use matchers:

| Matcher | Matches |
|---|---|
| `\Mockery::any()` | anything |
| `->withAnyArgs()` / `->withNoArgs()` | any argument list / an empty one |
| `\Mockery::type(Book::class)` / `type('int')` | by class or scalar type |
| `\Mockery::isSame($obj)` | identical instance |
| `\Mockery::pattern('/^\d+$/')` | regex |
| `\Mockery::on(fn($v) => $v > 100)` | closure on one argument |
| `->withArgs(fn(...$args) => ...)` | closure on the whole argument list |
| `\Mockery::capture($var)` | anything; stores the value into `$var` |
| `\Mockery::hasKey('id')` / `\Mockery::contains(1, 2)` / `\Mockery::subset([...])` | array shape checks |
| `\Mockery::not(5)` / `\Mockery::anyOf(1, 2)` | negation / alternatives |
| `->andAnyOtherArgs()` | trailing arguments (must be last) |

### Spy

```php
/** @var MockInterface&Mailer $mailer */
$mailer = \Mockery::spy(Mailer::class);
$service = new Signup($mailer);

$service->register('alice@example.com');

$mailer->shouldHaveReceived('send')->with(\Mockery::type(WelcomeMail::class))->once();
$mailer->shouldNotHaveReceived('sendSms');
```

A spy returns `null` from unconfigured calls; add `allows()->andReturn()` where the SUT needs a value.
`$spy->shouldNotHaveBeenCalled()` asserts the whole double was untouched.

### Mock

```php
/** @var MockInterface&Connection $conn */
$conn = \Mockery::mock(Connection::class);
$conn->expects('open')->ordered();
$conn->expects('write')->with('payload')->ordered();
$conn->expects('close')->ordered();

(new Exporter($conn))->run('payload');
```

Counts: `once()`, `twice()`, `times(3)`, `never()`, `atLeast()->once()`, `atMost()->times(5)`,
`between(1, 3)`, `zeroOrMoreTimes()`. `ordered()` sequences the marked expectations of one mock;
`ordered()->globally()` sequences across mocks.

### Strict vs loose

`\Mockery::mock()` is strict: an unconfigured call throws. `->shouldIgnoreMissing()` makes it loose
(returns `null`), `->shouldIgnoreMissing()->asUndefined()` returns a null object you can keep chaining on.
`byDefault()` marks an expectation as a fallback a later, more specific one may replace.

### Partial

```php
$calc = \Mockery::mock(PriceCalculator::class, [$taxTable])->makePartial();   // ctor args, real code for the rest
$calc->allows('now')->andReturn(new \DateTimeImmutable('2030-01-01'));

$calc = \Mockery::mock(new PriceCalculator($taxTable));                          // proxied partial: wraps an instance, works for final classes
$calc = \Mockery::mock('PriceCalculator[now]');                                  // generated partial: only `now` is mockable
```

`passthru()` on an expectation runs the real method while still counting the call.

### Multiple interfaces

```php
$logger = \Mockery::mock(LoggerInterface::class, FlushableInterface::class);
```

## 4. Pitfalls

- **No `Mockery::close()`, no `tearDown()`, no `MockeryPHPUnitIntegration`.** The plugin does all of it.
  A manual `close()` inside the test verifies early and the teardown then sees an empty container.
- **`shouldReceive()` without a count is a stub, not a mock.** It never fails on zero calls. Use
  `expects()` or add `once()`.
- **`alias:` and `overload:` mocks** replace a class for the whole process and Mockery requires process
  isolation for them. Testo runs tests in one process — avoid them; wrap the static dependency instead.
- **`final` classes** can only be doubled as a proxied partial (`\Mockery::mock(new Foo)`), which does not
  pass `instanceof Foo` type checks on the SUT's parameter. Extract an interface. Enums cannot be doubled.
- **Loose comparison.** `with(1)` matches a call with `'1'`. Use `\Mockery::isSame()` or a typed matcher
  when the type matters.
- **Spies return `null`.** A SUT that needs a value from the spied collaborator gets a `TypeError` unless
  you `allows()->andReturn()` that method.
- **Expectations reset per test.** Doubles built in `#[BeforeTest]` live for one test; doubles built in
  `#[BeforeClass]` are cleared by the first test's teardown and misbehave afterwards — build them per test.
