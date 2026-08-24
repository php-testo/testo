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

## Implemented since the first cut

- **MarkTestIncompleteRector** (registered) — Testo has no dedicated "incomplete" status, so
  `$this->markTestIncomplete($m)` (also `self::`/`static::`) maps to the nearest one: a
  `throw new \Testo\Core\Exception\SkipTest(...)` (Skipped). Both statuses neither pass nor fail and
  halt the test at the call site, so runtime behaviour coincides. **Lossy by design:** the "unfinished
  test" nuance PHPUnit draws between Incomplete and Skipped is preserved only as an `Incomplete: `
  prefix on the reason — a literal message folds into `'Incomplete: <msg>'`, a non-literal message
  becomes `'Incomplete: ' . $expr` (evaluated once), and a bare `markTestIncomplete()` yields
  `'Incomplete'`. The prefix keeps the distinction visible and re-detectable by a future reverse rule
  rather than vanishing silently.
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
- **MergeAssertChainRector** (registered) — a cleanup pass that collapses adjacent
  `\Testo\Assert::<type>($var)->…` fluent chains sharing an **identical** typed head (same
  `Assert::<type>` method applied to the same single **variable**) into one chain, concatenating the
  matcher tails: `Assert::array($l)->hasKeys('a'); Assert::array($l)->isList();` →
  `Assert::array($l)->hasKeys('a')->isList();`. Faithful — the subject is an unchanged variable, so
  the elided repeat type-checks were redundant (a wrong type would have thrown at the first head);
  only the count of recorded type-assertion successes drops, not the pass/fail outcome. Conservative:
  the subject must be a plain variable (never a call/property fetch, whose repeated evaluation could
  have side effects), the head must take exactly that one argument (so comparison/needle assertions
  like `Assert::count($v, 2)` / `Assert::instanceOf($v, X)` are never touched), and a different
  variable, a different type head, or any intervening statement ends the run. **Residual (by
  design):** does NOT fold the flat facade calls emitted by `AssertCallToTestoRector` — those
  (`same`/`true`/`count`/…) are `void` static calls or would need a typed head that turns a `TypeError`
  into an `AssertionException`, changing the failure status; so converted PHPUnit `assert*` runs stay
  as separate flat lines. This rule only tidies pre-existing typed pipes.
- **TypedAssertCallToTestoRector** (registered) — the sibling of `AssertCallToTestoRector` for the
  assertions whose faithful Testo form is a **typed head + matcher** rather than a flat facade call.
  The subject moves from an argument to the head argument (evaluated once, no hoisting needed):
  comparisons (`assertGreaterThan($e, $a)`→`Assert::numeric($a)->greaterThan($e)`, plus
  `GreaterThanOrEqual`/`LessThan`/`LessThanOrEqual`), array keys (`assertArrayHasKey($k, $a)`→
  `Assert::array($a)->hasKeys($k)`, and `assertArrayNotHasKey`→`doesNotHaveKeys`), and
  `assertEqualsCanonicalizing($e, $a)`→`Assert::array($a)->sameElementsAs($e)`. `assertEmpty`/
  `assertNotEmpty` map to the flat `Assert::blank()`/`notBlank()` **only for an array subject** (via
  PHPStan type inference): `blank()` treats `false`/`0`/`'0'` as valid data, so those notions coincide
  with PHP's `empty()` only where the subject can never be one of them — an array. A non-array (or
  statically-unknown) subject is left untouched. Same "only inside a class" gate as
  `AssertCallToTestoRector`. **Message residual (by design):** the numeric matchers,
  `sameElementsAs()`, `blank()`/`notBlank()` all keep a trailing `$message` — but the array-key
  matchers (`hasKeys`/`doesNotHaveKeys`) are variadic with no message parameter, so a PHPUnit message
  on `assertArrayHasKey`/`assertArrayNotHasKey` is dropped (mirrors the reverse direction, which emits
  keyed assertions without a message). `assertNotEqualsCanonicalizing` has no counterpart (there is no
  `notSameElementsAs`) and is left untouched.
- **RepeatRetryToTestoRector** (registered) — converts PHPUnit's `#[Repeat]` / `#[Retry]` method
  attributes (PHPUnit 13.3+) into `#[\Testo\Repeat]` / `#[\Testo\Retry]`. `times`/`maxAttempts` carry
  over verbatim; PHPUnit's `failureThreshold` (aborting failure count, default 1) maps to Testo's
  `maxFailures` (tolerated failures, default 0) as `maxFailures = failureThreshold - 1`, and the
  PHPUnit default 1 folds to the Testo default 0 (omitted). Faithful with no target reconciliation —
  PHPUnit's attributes are method-only and Testo's accept a strict superset of targets.

The imperative body rules — `AssertCallToTestoRector`, `ExpectExceptionToTestoRector`,
`MarkTestSkippedToTestoRector`, `MarkTestIncompleteRector`, `MergeAssertChainRector` — fire **only
inside a class** (PHPStan `Scope::isInClass()`), mirroring the Testo → PHPUnit direction. Assertions, skips and exception
expectations belong to a test method (or a static data provider); a matching call in a free function
or at namespace level is left untouched. Each rule carries an `outside_method_left_unchanged` fixture
proving the no-op. (Unlike the reverse direction the outputs — static `\Testo\Assert::*`/`\Testo\Expect::*`
calls and `throw` — are valid anywhere, so this is a scoping/consistency choice, not a fatal-avoidance one.)
