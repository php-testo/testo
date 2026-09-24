# Mockery -> Double: outstanding / partial conversions

The `mockery-to-double` set follows Double's [Mockery migration table](https://testdoublephp.com/migrating-from-mockery),
checked against both libraries' sources. It does not depend on the test framework.

## Implemented

- **MockeryToDoubleRector** (registered) — see the class docblock for the full map. Where it departs
  from the migration table, on purpose:
  - `Mockery::mock(X)` → `Double::for(X)->strict()`, not a bare `Double::for(X)`. A plain Mockery mock
    throws on an unconfigured call; Double's default Loose mode answers it. Strict keeps the failure.
    `Mockery::spy(X)` and `->shouldIgnoreMissing()` are the Loose ones.
  - `shouldReceive('m')->once()` → `expects('m')` with no `times(1)`: `expects()` means exactly once.
  - `allows('m')` / `expects('m')` are Double verbs too, so such a chain converts only when it carries
    a Mockery-only link (`andReturn`, `once`, `withArgs`, …). A Double chain is never rewritten.
  - `shouldNotHaveBeenCalled()` is not `unused()`: Mockery means "`__invoke` was never called", so it
    becomes `received('__invoke')->never()`; `shouldHaveBeenCalled()` is `received('__invoke')`.
  - `Mockery::type()` checks `is_<name>()` when that function exists: the names Double's `type()`
    spells differently are normalised (`integer`/`long` → `int`, `double`/`real` → `float`), and the
    checks Double has no type name for (`numeric`, `scalar`, `resource`, `countable`, …) become a
    `satisfies()` predicate.
  - Statements that set up several expectations at once are split: `shouldReceive('a', 'b')`, the
    `['m' => $return]` maps of `shouldReceive()`/`allows()`, the quick definitions
    `$m = Mockery::mock(X, ['m' => 1])`, and `$m = Mockery::mock(X)->shouldReceive(...)->...->getMock()`.
    Several methods repeat every link, so their arguments must be side-effect free (values, variables,
    closures, matchers).
  - `byDefault()` drops when the expectation has no arguments and no count: Double tries the newest
    expectation first, so a later one overrides it. With arguments or a count it would keep answering
    calls Mockery sends past it, so it stays.
  - `hasKey`, several-value `contains`, `subset` and `ducktype` become the `satisfies()` predicate
    Mockery's own matcher evaluates.
  - A computed target converts by its PHPStan type: a class-string as a target, an object as the proxied
    partial `Double::for($object)->passthru()`.
- **MockeryIntegrationToDoubleRector** (registered) — `use MockeryPHPUnitIntegration` → `use
  VerifiesDoubles`, and `extends MockeryTestCase` → `extends TestCase` plus the trait, for suites that stay
  on PHPUnit. A class overriding `mockeryTestSetUp()`/`mockeryTestTearDown()`, or adapting the trait, is
  left alone.

## Left for manual migration

- Comparison strictness (by design): Mockery compares plain values with `==`, Double with `===` for
  scalars. A test relying on coercion (`'1'` against `1`) fails after conversion; fix the expected value.
- A proxied partial: `Double::for($object)->passthru()` copies the object's state, while Mockery's proxy
  forwards to the object itself, so a test reading the original object after the calls sees a difference.
- A bare `Mockery::mock()`, a full mock with constructor arguments, a closure target, a computed target
  of unknown type, and the string targets `alias:`, `overload:`, `X[m]`: Double needs a real type and
  has no aliases, instance mocks or static mocking.
- `passthru()`, `andSet()`, `andReturnUndefined()`, `globally()`, grouped `ordered('g')`, demeter
  `shouldReceive('a->b')`, several `andReturnUsing()` closures, `byDefault()` with arguments or a count.
- Matchers with no Double form: a matcher nested in another, a computed `subset()` strictness flag,
  Hamcrest matchers.
- A statement is converted whole or not at all, but a double is not tracked across statements: when
  one statement on a mock stays Mockery while its factory converts, that statement needs a hand fix.
