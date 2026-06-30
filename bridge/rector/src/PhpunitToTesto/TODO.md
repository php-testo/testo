# PHPUnit -> Testo: outstanding / partial conversions

The `phpunit-to-testo` set converts the faithful, mechanical cases. The items below
are either impossible to convert automatically or too fragile to automate safely.
Stub rules (`refactor()` returns `null`, no `#[TestRectorFixtures]`, not registered)
exist for each so the intent and blockers are discoverable in code.

## Stubbed (not registered)

- **MockToTestoRector** — `createMock`/`getMockBuilder`/`createStub`/`prophesize`:
  Testo ships no built-in mocking, so there is no target API. Replace manually with a
  third-party mocking library or hand-written fakes.
- **AssertThatConstraintRector** — `assertThat($v, $constraint)`: relies on PHPUnit
  constraint objects (and composites/callbacks) with no Testo equivalent.
- **ExpectExceptionMessageMatchesRector** — regex message matching; Testo's
  `withMessage()` does literal matching, not PCRE, so conversion would change meaning.
- **MarkTestIncompleteRector** — Testo has no "incomplete" status (only Skipped via
  `SkipTest`); mapping it onto skip would lose the distinction.

## Implemented since the first cut

- **ExtendsTestCaseToTestoRector** (registered) — removes a **direct** `extends
  \PHPUnit\Framework\TestCase` and makes the class attribute-discoverable: each test method gains
  `#[\Testo\Test]`. "Test method" mirrors PHPUnit discovery — a `#[\PHPUnit\Framework\Attributes\Test]`
  attribute (renamed in place to `#[\Testo\Test]`), a `@test` docblock annotation (tag removed, attribute
  added), or a `test`-prefixed method name (attribute added). Idempotent (skips a method already carrying
  `#[\Testo\Test]`). **Residuals:** (1) only a class extending `TestCase` *directly* is converted — an
  intermediate/custom base is left untouched (convert it at the base); (2) methods are NOT renamed —
  Testo discovers by attribute, so keeping `testFoo()` is harmless, and prefix cleanup / call-site
  rewriting is left manual.
- **ExpectExceptionToTestoRector** (registered) — now folds the fluent chain, not just the bare
  head. It operates at the statements level (matches the enclosing `StmtsAwareInterface` node and
  rewrites its `->stmts`): after a `$this->expectException($c)` statement it absorbs the
  uninterrupted run of immediately-following sibling `expectExceptionMessage($m)` /
  `expectExceptionCode($n)` statements into `\Testo\Expect::exception($c)->withMessage($m)->withCode($n)`
  and removes them. Conservative: the run stops at the first non-foldable statement (including
  `expectExceptionMessageMatches`, whose regex has no `withMessage*` counterpart — see the stubbed
  `ExpectExceptionMessageMatchesRector`), statements are never reordered or pulled across other code,
  and a bare `expectExceptionMessage`/`Code` with no preceding `expectException` is left untouched.
- **GroupToTestoRector** (registered) — collapses every PHPUnit group source on a node — the
  `@group` docblock annotation(s) **and** the repeatable single-name `#[Group]` attribute(s) — into
  one variadic `#[\Testo\Filter\Group('a', 'b', …)]` (Testo's `Group` is variadic but not
  repeatable). Per-node and faithful: Testo re-derives the inheritance union at run time, so no
  cross-hierarchy work is needed in this direction.
- **DataProviderAnnotationToTestoRector** + **DataProviderAttributeToTestoRector** (both
  registered) — both source forms now convert directly to `#[\Testo\Data\DataProvider('method')]`:
  the `@dataProvider` docblock tag (mechanics adapted from Rector's own rule, minus the
  `TestCase` gate, so no `phpunit/phpunit` dependency) and the PHPUnit `#[DataProvider]` attribute.
  Cross-class providers (`Other::method` / `#[DataProviderExternal]`) are still left in place —
  Testo's `DataProvider` takes a single provider and the external form is rare.
- **DoesNotPerformAssertionsToTestoRector** (registered) — direct attribute rename
  `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]` → `#[\Testo\Assert\ExpectNoAssertions]`
  (equivalent "no assertions expected" markers).
