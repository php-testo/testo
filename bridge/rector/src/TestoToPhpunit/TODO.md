# Testo → PHPUnit: stubbed & partial conversions

Rules that are not (fully) implemented, with the reason. Stub rules live in this
directory but are **not** registered in `config/testo-to-phpunit.php`.

## Stubs (not registered)

- **`MemoryLeakExpectationRector`** — `Expect::notLeaks()/leaks()`: PHPUnit has no
  memory-leak assertion or post-test object-liveness hook.
- **`CancelTestRector`** — `CancelTest` is an external interruption signal with no
  PHPUnit equivalent (not the same as skip/incomplete).

## Implemented since the first cut

- **`TestClassToTestCaseRector`** (registered) — adds `extends \PHPUnit\Framework\TestCase` to a Testo
  test class and reconciles discovery. A class-level `#[\Testo\Test]` is removed and a per-method
  `#[\PHPUnit\Framework\Attributes\Test]` is added to every public, non-static method with a `void`/`never`
  return type — exactly the set Testo's locator treats as tests from a class-level marker, so static and
  `iterable`-returning providers/helpers are skipped. A method-level `#[\Testo\Test]` is renamed in place to
  the PHPUnit attribute; if that method is `static` it is also made an instance method (Testo invokes tests
  with or without an instance, but PHPUnit only ever calls them on one and flags a `static` `#[Test]` — a
  static body has no `$this`, so dropping `static` is behaviour-preserving; data providers keep `static` as
  they carry no `#[Test]`). Idempotent at method level. **Residuals:** (1) only a class that extends *nothing*
  is converted — adding `extends TestCase` to a class with an existing base would be a single-inheritance
  clash, so it is left untouched; (2) methods are NOT renamed (no `test` prefix is added — PHPUnit discovers
  the `#[Test]`-attributed method regardless of name).
- **`ExpectExceptionToPhpUnitRector`** (registered) — now converts the full chain, not just the
  bare head. It matches the wrapping `Stmt\Expression` and returns `Node[]`, expanding
  `\Testo\Expect::exception($c)->withMessage($m)->withCode($n)` into the separate PHPUnit statements
  `$this->expectException($c); $this->expectExceptionMessage($m); $this->expectExceptionCode($n);`.
  Mapped modifiers: `withMessage` → `expectExceptionMessage`, `withCode` → `expectExceptionCode`,
  regex `withMessagePattern` → `expectExceptionMessageMatches`. **Residual:** the substring matcher
  `withMessageContaining` is intentionally NOT mapped — PHPUnit's only message matcher
  (`expectExceptionMessageMatches`) takes a PCRE pattern, so forwarding a literal substring would
  change meaning; a chain using it (or any other unmapped modifier such as `fromMethod`/`withPrevious`)
  aborts the whole conversion and is left untouched for manual review.
- **`ExpectExceptionAttributeToPhpUnitRector`** (registered) — converts the attribute form
  `#[\Testo\Assert\ExpectException($class)]` into a `$this->expectException($class);` statement
  prepended to the method body (PHPUnit dropped its `ExpectException` attribute, leaving only the
  imperative call). Complements `ExpectExceptionToPhpUnitRector`, which handles the fluent
  `\Testo\Expect::exception(...)` call form. An attribute on a bodyless (abstract) method is just
  dropped.
- **`ExpectNoAssertionsToPhpUnitRector`** (registered) — direct attribute rename
  `#[\Testo\Assert\ExpectNoAssertions]` → `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`
  (the two are equivalent markers — the earlier "attribute → method call" stub was over-pessimistic).
  Both markers are method/function-level only (Testo's is no longer allowed on a class, matching
  PHPUnit's method-level `DoesNotPerformAssertions`), so the in-place rename is faithful with no
  class-level fan-out to reconcile.
- **`GroupToPhpUnitRector`** (registered) — expands a variadic `#[\Testo\Filter\Group('a', 'b')]`
  into the repeated, single-name PHPUnit form `#[Group('a'), Group('b')]`, per-node.
- **`GroupInheritanceToPhpUnitRector`** (registered) — the complement of the above: flattens Testo's
  group *inheritance union* onto the concrete (leaf) test class, at two levels. (1) *Class level*: it
  resolves ancestors via Rector's `ReflectionProvider` (parent classes recursively + used traits
  recursively), reads their class-level `#[\Testo\Filter\Group]` names from reflection, and adds the
  inherited names the leaf class does not already carry as repeated
  `#[\PHPUnit\Framework\Attributes\Group('x')]` attributes. (2) *Method level*: for each method declared
  on the leaf, it walks the prototype chain (the same-named method on parent classes, recursively —
  mirroring Testo's `\ReflectionMethod::getPrototype()` walk) and appends that parent method's
  `#[\Testo\Filter\Group]` names onto the leaf method. Idempotent and order-independent at both levels:
  "already present" is checked against both the unconverted Testo form and the converted PHPUnit form,
  so it coexists with `GroupToPhpUnitRector` regardless of rule order. Ancestor classes/traits are never
  modified. **Residual:** traits are intentionally NOT consulted at method level — when a leaf method
  overrides a same-named trait method PHP silently lets the class method win and `getPrototype()` is
  `false`, so Testo's filter never sees the trait method's groups for that override either; the
  conversion matches Testo exactly rather than inventing a trait override-union Testo does not perform.
- **`DataProviderToPhpUnitRector`** (registered) — the mirror of `DataProviderAttributeToTestoRector`:
  renames `#[\Testo\Data\DataProvider('m')]` → `#[\PHPUnit\Framework\Attributes\DataProvider('m')]` and
  `#[\Testo\Data\DataSet([…], 'label')]` → `#[\PHPUnit\Framework\Attributes\TestWith([…], 'label')]`. Both
  Testo attributes and both PHPUnit targets are repeatable and share their argument shape (`DataSet`/`TestWith`
  are `(array $data, ?string $name = null)`), so arguments are preserved verbatim — only the attribute class
  changes.
- **`TypedAssertChainRector`** (registered) — decomposes fluent typed assertions
  (`Assert::string()->contains()`, `Assert::int()->between()`, `Assert::array()->hasKeys()`,
  `Assert::array()->isList()`→`assertIsList`, `Assert::array()->sameElementsAs()`→`assertEqualsCanonicalizing`,
  …) into separate `assert*` statements, expanding 1→N where needed. A non-variable subject (e.g.
  `Assert::array($log->all())->isList()`) is hoisted into a scope-safe `$value` local so it is
  evaluated once. Matchers with no faithful PHPUnit line (JSON path/structure, `every`, `sameSizeAs`,
  custom) leave the whole chain untouched rather than half-converting.
- **`RepeatRetryRector`** (registered) — converts **method-level** `#[\Testo\Repeat]` /
  `#[\Testo\Retry]` into PHPUnit's `#[Repeat]` / `#[Retry]` (available since PHPUnit 13.3). Testo's
  `maxFailures` (tolerated failures, default 0) maps to PHPUnit's `failureThreshold` (aborting failure
  count, default 1) as `failureThreshold = maxFailures + 1`; the Testo default 0 folds back to the
  PHPUnit default 1 and is omitted. Testo's defaults are made explicit where PHPUnit lacks a matching
  drop (`times`→2, `maxAttempts`→3), and the emitted arguments are positional (PHPUnit's attributes are
  `@no-named-arguments`). PHPUnit's `Repeat`/`Retry` are `TARGET_METHOD` only, so a **class-level** Testo
  attribute is fanned out onto each test method (mirroring how Testo applies it to every test in the
  class) and removed from the class; a method carrying its own attribute of the same kind keeps it
  (method-level overrides the class default, not doubled). Test methods are found as in
  `TestClassToTestCaseRector` — the `#[Test]`-marked methods, or under a class-level `#[\Testo\Test]`
  every public non-static `void`/`never` non-lifecycle method. **Residual:** Testo's `markFlaky` flag is
  dropped (no PHPUnit equivalent); a function-level attribute (a free-function test) has no PHPUnit
  target and is left untouched.

All four imperative body rules — `AssertCallToPhpUnitRector`, `TypedAssertChainRector`,
`ExpectExceptionToPhpUnitRector`, `ThrowSkipTestToPhpUnitRector` — now fire **only inside a class**
(PHPStan `Scope::isInClass()`). Their output (`$this->…` / `self::…`) needs a method scope; a call or
throw in a free function or at namespace level has no valid target, so it is left untouched instead
of being converted into code that would fatal. Each rule carries an `outside_method_left_unchanged`
fixture proving the no-op.
