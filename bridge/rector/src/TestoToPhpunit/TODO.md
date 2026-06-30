# Testo → PHPUnit: stubbed & partial conversions

Rules that are not (fully) implemented, with the reason. Stub rules live in this
directory but are **not** registered in `config/sets/testo-to-phpunit.php`.

## Stubs (not registered)

- **`MemoryLeakExpectationRector`** — `Expect::notLeaks()/leaks()`: PHPUnit has no
  memory-leak assertion or post-test object-liveness hook.
- **`RepeatRetryRector`** — `#[Repeat]`/`#[Retry]`: PHPUnit core has no repeat/retry
  attribute or loop.
- **`CancelTestRector`** — `CancelTest` is an external interruption signal with no
  PHPUnit equivalent (not the same as skip/incomplete).

## Implemented since the first cut

- **`TestClassToTestCaseRector`** (registered) — adds `extends \PHPUnit\Framework\TestCase` to a Testo
  test class and reconciles discovery. A class-level `#[\Testo\Test]` is removed and a per-method
  `#[\PHPUnit\Framework\Attributes\Test]` is added to every public, non-static method with a `void`/`never`
  return type — exactly the set Testo's locator treats as tests from a class-level marker, so static and
  `iterable`-returning providers/helpers are skipped. A method-level `#[\Testo\Test]` is renamed in place to
  the PHPUnit attribute. Idempotent at method level. **Residuals:** (1) only a class that extends *nothing*
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
- **`ExpectNoAssertionsToPhpUnitRector`** (registered) — direct attribute rename
  `#[\Testo\Assert\ExpectNoAssertions]` → `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`
  (the two are equivalent markers — the earlier "attribute → method call" stub was over-pessimistic).
  **Residual:** Testo's marker may sit at class level, but PHPUnit's `DoesNotPerformAssertions` is
  method-level; a class-level marker rewritten in place is not honored by PHPUnit and ideally would
  fan out onto each test method — left as a `@todo`.
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
  (`Assert::string()->contains()`, `Assert::int()->between()`, `Assert::array()->hasKeys()`, …)
  into separate `assert*` statements, expanding 1→N where needed. Matchers with no faithful
  PHPUnit line (JSON path/structure, `isList`, `every`, `sameSizeAs`, custom) leave the whole
  chain untouched rather than half-converting.
