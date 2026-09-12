# Doubles with Double (`testo/bridge-double`)

Reference for the Double route of `testo-test-doubles`. [Double](https://github.com/jasonmccreary/double)
is one object type that acts as stub, spy, mock or partial depending on which verbs you call; the bridge
verifies every double at teardown. Full library docs: <https://testdoublephp.com>.

## 1. Pre-flight

Read the `DOUBLE` block of `scripts/precheck.php` (run from `SKILL.md` Step 2). It reports three facts:

| Row | Meaning when `NO` |
|---|---|
| `jasonmccreary/double` | Library missing → §2 |
| `testo/bridge-double` | Bridge missing → §2. The library alone never verifies under Testo. |
| `DoublePlugin registered` | `testo.php` does not mention `DoublePlugin` → §2.2. Expectations go unverified; mock-only tests come out `Risky`. |

Also check the PHP row: Double needs **PHP 8.3+**. On 8.2 this route is closed — use Mockery or
hand-written fakes.

## 2. Install

### 2.1 Packages

```bash
composer require --dev testo/bridge-double
```

The bridge pulls `jasonmccreary/double` in; nothing else to require.

### 2.2 Register the plugin

Application-wide (every suite):

```php
// testo.php
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Double\DoublePlugin;

return new ApplicationConfig(
    plugins: [new DoublePlugin()],
    suites:  [new SuiteConfig(name: 'Unit', location: ['tests/Unit'])],
);
```

Per suite, when only some suites use doubles:

```php
use Testo\Application\Config\Plugin\SuitePlugins;

new SuiteConfig(
    name: 'Unit',
    location: ['tests/Unit'],
    plugins: SuitePlugins::with(new DoublePlugin()),
),
```

Once registered, `Double::verifyAll()` runs after every test: unmet `expects()` and deferred `received()`
checks fail the test, the pending set is cleared, and each resolved check is mirrored into the Assert
history. Re-run the pre-flight; the `DOUBLE` verdict must read `READY`.

## 3. Usage

`Double::for(Target::class)` returns a real instance of `Target` (passes `instanceof`) that also implements
`DoubleInterface`. Type it as the intersection so analysers see both:

```php
use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;

/** @var DoubleInterface&BookRepository $repo */
$repo = Double::for(BookRepository::class);
```

Two verbs configure calls. **`allows('m')`** — may be called any number of times, including zero (stub).
**`expects('m')`** — must be called, exactly once unless `times()` says otherwise (mock). Everything else
chains off them.

### Dummy

```php
$logger = Double::for(LoggerInterface::class);      // loose by default: every call returns a safe default
$service = new Checkout($gateway, $logger);
```

### Stub

```php
/** @var DoubleInterface&BookRepository $repo */
$repo = Double::for(BookRepository::class);
$repo->allows('find')->with(123)->returns($book);
$repo->allows('find')->with(999)->throws(new NotFound());
$repo->allows('next')->returns($first, $second);       // consecutive calls; last value repeats
$repo->allows('price')->resolves(fn(int $id) => $id * 10);

$service = new Catalog($repo);

Assert::same($service->title(123), 'Dune');
```

Argument matching for `with()` — literal scalars/arrays compare with `===`, objects with `==`, or use
`JMac\Testing\Matching\Argument`:

| Matcher | Matches |
|---|---|
| `Argument::any()` / `Argument::any(1, 2)` | anything / one of the listed values |
| `Argument::none()` | a call with zero arguments |
| `Argument::type(Book::class)` / `type('int')` | by class or scalar type |
| `Argument::same($obj)` | identical instance |
| `Argument::matches('/^\d+$/')` | regex on a string or `Stringable` |
| `Argument::contains($needle)` | iterable holding the needle (needle may itself be a matcher or closure) |
| `Argument::satisfies(fn($v) => $v > 100)` | closure on one argument |
| `Argument::all(fn(...$args) => ...)` | closure on the whole argument list |
| `Argument::capture($var)` | anything; stores the actual value into `$var` by reference |
| `Argument::remaining()` | any trailing arguments (must be last) |
| `Argument::not(5)` / `Argument::not()->type('int')` | negation |

### Spy

Configure with `allows()`, act, then inspect with **`received()`**. The check runs when the statement
ends, so a `received()` line is itself the assertion:

```php
/** @var DoubleInterface&Mailer $mailer */
$mailer = Double::for(Mailer::class);
$service = new Signup($mailer);

$service->register('alice@example.com');

$mailer->received('send')->with(Argument::type(WelcomeMail::class))->times(1);
$mailer->received('sendSms')->never();
```

`$double->unused()` asserts no method was called on the double at all — the strongest spy check, useful
for "this branch must not touch the gateway".

### Mock

Declare with `expects()` before the act; the bridge verifies at teardown:

```php
/** @var DoubleInterface&Connection $conn */
$conn = Double::for(Connection::class);
$conn->expects('open')->ordered();
$conn->expects('write')->with('payload')->ordered();
$conn->expects('close')->ordered();

(new Exporter($conn))->run('payload');
```

Counts: `times(3)` exact, `times(1, 3)` range, `times(minimum: 2)`, `times(maximum: 5)`, `never()`.
`ordered()` enforces sequence among the marked expectations of **one** double; unordered ones are
unaffected.

### Strict double

```php
$repo = Double::for(BookRepository::class)->strict();   // any unconfigured call throws immediately
```

### Partial (passthru)

Unconfigured calls run the real code. Wrap an instance so its state is copied in:

```php
/** @var DoubleInterface&PriceCalculator $calc */
$calc = Double::for(new PriceCalculator($taxTable))->passthru();
$calc->allows('now')->returns(new \DateTimeImmutable('2030-01-01'));
```

`passthru()` on an interface target needs a real instance: `Double::for(Iface::class)->passthru($real)`.
Internal `$this->now()` calls inside the real method do reach the stub.

### Multiple interfaces

```php
$logger = Double::for(LoggerInterface::class, FlushableInterface::class);   // all but the first must be interfaces
```

## 4. Pitfalls

- **`expects()` means exactly once.** For "at least once" write `expects('m')->times(minimum: 1)`; for
  "any number, verify nothing" use `allows()`.
- **`final` targets** need `Double::bypassFinals()` in the bootstrap before the class is autoloaded. Prefer
  extracting an interface; a `final` class is a design signal, not an obstacle. Enums cannot be doubled.
- **Reserved names.** `expects`, `allows`, `strict`, `passthru`, `received`, `unused`, `verify` on the
  target collide with the configuration verbs. Pass `override: true` and hand `$double->instance()` to the
  SUT: `$gate = Double::for(Authorizer::class, override: true); new Checker($gate->instance());`.
- **`received()` is deferred to the end of its statement** — never wrap it in a condition or store the
  chain in a variable without completing it.
- **Comparison is `===`.** A stub configured `with(1)` does not match a call with `'1'`. When migrating
  from Mockery (loose `==`) this surfaces hidden type bugs; fix the test's expected value, not the SUT.
- **No `byDefault()`, no global ordering, no aliases.** Each concept has one verb. Multiple `allows()` on
  the same method with different `with()` coexist; the matching one answers.
- **Statics and magic methods** are not doubled (except `__invoke`, `__toString`, `__serialize`,
  `__unserialize`, `__clone`).
- **Never call `Double::verifyAll()` or `->verify()` yourself** under the bridge — teardown does it, and a
  manual call empties the pending set early.
