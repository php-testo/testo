# Testo → PHPUnit: stubbed & partial conversions

Rules that are not (fully) implemented, with the reason. Stub rules live in this
directory but are **not** registered in `config/sets/testo-to-phpunit.php`.

## Partial (registered, but incomplete)

- **`ExpectExceptionToPhpUnitRector`** — converts only the bare
  `\Testo\Expect::exception($class)` head into `$this->expectException($class)`.
  The fluent modifiers `->withMessage($m)` → `$this->expectExceptionMessage($m)`,
  `->withMessageContaining($s)` / `->withMessagePattern($re)` →
  `$this->expectExceptionMessageMatches($re)`, and `->withCode($n)` →
  `$this->expectExceptionCode($n)` are NOT converted: each becomes a separate
  PHPUnit statement, so one chained expression must be expanded into several
  statements (match the wrapping `Stmt\Expression` and return `Node[]`). This is now a
  proven pattern — see `TypedAssertChainRector` — so finishing the fluent modifiers is a
  tractable follow-up, not a blocker.

## Stubs (not registered)

- **`TestClassToTestCaseRector`** — Testo classes need no base class and may use a
  class-level `#[Test]`; adding `extends TestCase` and reconciling method discovery
  is a whole-class structural transform, not a local rewrite.
- **`MemoryLeakExpectationRector`** — `Expect::notLeaks()/leaks()`: PHPUnit has no
  memory-leak assertion or post-test object-liveness hook.
- **`RepeatRetryRector`** — `#[Repeat]`/`#[Retry]`: PHPUnit core has no repeat/retry
  attribute or loop.
- **`CancelTestRector`** — `CancelTest` is an external interruption signal with no
  PHPUnit equivalent (not the same as skip/incomplete).

## Implemented since the first cut

- **`ExpectNoAssertionsToPhpUnitRector`** (registered) — direct attribute rename
  `#[\Testo\Assert\ExpectNoAssertions]` → `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`
  (the two are equivalent markers — the earlier "attribute → method call" stub was over-pessimistic).
  **Residual:** Testo's marker may sit at class level, but PHPUnit's `DoesNotPerformAssertions` is
  method-level; a class-level marker rewritten in place is not honored by PHPUnit and ideally would
  fan out onto each test method — left as a `@todo`.
- **`GroupToPhpUnitRector`** (registered) — expands a variadic `#[\Testo\Filter\Group('a', 'b')]`
  into the repeated, single-name PHPUnit form `#[Group('a'), Group('b')]`, per-node.
- **`GroupInheritanceToPhpUnitRector`** (registered) — the complement of the above: flattens Testo's
  group *inheritance union* onto the concrete (leaf) test class. For a non-abstract class it resolves
  ancestors via Rector's `ReflectionProvider` (parent classes recursively + used traits recursively),
  reads their class-level `#[\Testo\Filter\Group]` names from reflection, and adds the inherited names
  the leaf does not already carry as repeated `#[\PHPUnit\Framework\Attributes\Group('x')]`
  attributes. Idempotent and order-independent: "already present" is checked against both the
  unconverted Testo form and the converted PHPUnit form, so it coexists with `GroupToPhpUnitRector`
  regardless of rule order. Ancestor classes are never modified. **Residual:** only *class-level*
  hierarchy is flattened — *method-level* override-union (a leaf method inheriting groups from the
  same method on a parent/trait) is not implemented (left as a `@todo` in the rule); class-level
  inheritance is the common case and the priority.
- **`TypedAssertChainRector`** (registered) — decomposes fluent typed assertions
  (`Assert::string()->contains()`, `Assert::int()->between()`, `Assert::array()->hasKeys()`, …)
  into separate `assert*` statements, expanding 1→N where needed. Matchers with no faithful
  PHPUnit line (JSON path/structure, `isList`, `every`, `sameSizeAs`, custom) leave the whole
  chain untouched rather than half-converting.
