# PHPUnit -> Testo: outstanding / partial conversions

The `phpunit-to-testo` set converts the faithful, mechanical cases. The items below
are either impossible to convert automatically or too fragile to automate safely.
Stub rules (`refactor()` returns `null`, no `#[TestRectorFixtures]`, not registered)
exist for each so the intent and blockers are discoverable in code.

## Partial (registered, but incomplete)

- **ExpectExceptionToTestoRector** — converts the bare `$this->expectException($c)`
  to `\Testo\Expect::exception($c)`, but does NOT fold consecutive
  `expectExceptionMessage()` / `expectExceptionCode()` calls into a fluent
  `->withMessage()` / `->withCode()` chain. That requires cross-statement reasoning
  (grouping sibling calls, ordering), which is fragile node-locally. Such calls are
  left in place and must be merged manually.

## Stubbed (not registered)

- **ExtendsTestCaseToTestoRector** — removing `extends PHPUnit\Framework\TestCase`
  and marking the class with `#[\Testo\Test]`: test-discovery models differ (PHPUnit
  `test`-prefix/`#[Test]` vs Testo `#[\Testo\Test]`); dropping the base class would
  orphan every test method unless each is also attributed/renamed. Invasive and
  order-sensitive against the other rules.
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
