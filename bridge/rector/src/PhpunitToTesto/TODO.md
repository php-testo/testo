# PHPUnit -> Testo: outstanding / partial conversions

The `phpunit-to-testo` set converts the faithful, mechanical cases. The items below
are either impossible to convert automatically or too fragile to automate safely.
Stub rules (`refactor()` returns `null`, no `#[TestRectorFixtures]`, not registered)
exist for each so the intent and blockers are discoverable in code.

Mocks are out of this set: they convert through `phpunit-to-double` or `phpunit-to-mockery`, tracked in
`../PhpunitToDouble/TODO.md` and `../PhpunitToMockery/TODO.md`.

## Stubbed (not registered)

- **AssertThatConstraintRector** — `assertThat($v, $constraint)`: relies on PHPUnit
  constraint objects (and composites/callbacks) with no Testo equivalent.

## Implemented since the first cut

- **MarkTestSkippedToSkipAttributeRector** (registered) — a `markTestSkipped('literal')` opening a test
  method is a statement about the test, not about a path through it, so it becomes `#[\Testo\Skip('literal')]`
  and the call is dropped. That buys what the throw cannot: Testo keeps such a test out of the per-test
  pipeline entirely (no `#[BeforeTest]`, no data-provider call, no retries). Everything else keeps
  converting to a `SkipTest` throw via `MarkTestSkippedToTestoRector`, which is registered right after
  it — a guarded call, one deeper in the body, and a non-literal message no attribute argument can hold.
  A call opening `setUp()` also stays a throw: hoisting it to a class-level `#[Skip]` would strip the rest
  of the hook.
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
  `#[\Testo\Test]`). A detached class has no parent, so `#[\Override]` is dropped from every method an
  implemented interface does not declare (`setUp()` and other `TestCase` hooks); an unresolvable
  interface keeps all of them. A class reaching `TestCase` through an intermediate base keeps its `extends` and
  `#[\Override]` and only gains the `#[\Testo\Test]` marks, as do the non-abstract test methods of a trait. **Residual:** methods are NOT renamed —
  Testo discovers by attribute, so keeping `testFoo()` is harmless, and prefix cleanup / call-site
  rewriting is left manual.
- **ExpectExceptionToTestoRector** (registered) — now folds the fluent chain, not just the bare
  head. It operates at the statements level (matches the enclosing `StmtsAwareInterface` node and
  rewrites its `->stmts`): after a `$this->expectException($c)` statement it absorbs the
  following sibling `expectExceptionMessage($m)` /
  `expectExceptionMessageMatches($re)` / `expectExceptionCode($n)` statements into
  `\Testo\Expect::exception($c)->withMessageContaining($m)->withMessageMatchingRegex($re)->withCode($n)`
  and removes them. PHPUnit's `expectExceptionMessage()` matches a substring, hence
  `withMessageContaining()` rather than the exact `withMessage()`. Conservative: only assignments that
  cannot throw (`$message = '…';`) may stand between the calls, and the chain takes the place of the
  last absorbed one after them; any other statement ends the run,
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
  variable, a different type head, or any intervening statement ends the run. Two string chains
  merge only when they open with the same comparison modifiers (`ignoringCase()`,
  `ignoringWhitespace(...)`, …, arguments compared as written), since a modifier applies to every
  check after it; a chain with a modifier after a check never merges. **Residual (by
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
  with PHP's `empty()` only where the subject can never be one of them — an array. A subject that
  cannot be an object gets `Assert::true(empty($x))`/`Assert::false(empty($x))`, PHPUnit's own check;
  an object or statically-unknown subject is left untouched, since PHPUnit counts a `Countable`.
  `assertStringStartsWith`/`EndsWith` map to `Assert::string($s)->startsWith()`/`endsWith()`,
  `assertStringStartsNotWith`/`EndsNotWith` to `notStartsWith()`/`notEndsWith()` (PHPUnit throws for an
  empty prefix or suffix, where `startsWith('')` passes and `notStartsWith('')` fails),
  `assertStringContainsString`/`NotContainsString` to `Assert::string($s)->contains()`/`notContains()`
  (an empty needle is contained on both sides), `assertStringContainsStringIgnoringCase`/
  `NotContainsStringIgnoringCase` to `Assert::string($s)->ignoringCase()->contains()`/`notContains()`,
  `assertStringContainsStringIgnoringLineEndings` to `Assert::string($s)->ignoringLineEndings()->contains()`
  (both sides turn `\r\n` and `\r` into `\n` in needle and haystack),
  `assertStringEqualsStringIgnoringLineEndings` to `Assert::string($s)->ignoringLineEndings()->same()`,
  `assertStringEqualsStringIgnoringWhitespace`/`NotEquals` to
  `Assert::string($s)->ignoringWhitespace(lineBreaks: true)->same()`/`notSame()`, and
  `assertEqualsIgnoringCase`/`NotEqualsIgnoringCase` to `Assert::string($a)->ignoringCase()->same()`/
  `notSame()` when both sides are strings (both lowercase with `mb_strtolower()` and compare with
  `===`), `assertMatchesRegularExpression`/
  `DoesNotMatchRegularExpression` and the PHPUnit 9 `assertRegExp`/`assertNotRegExp` to
  `Assert::string($s)->matchesRegex()`/`notMatchesRegex()` (full PCRE on both sides; an invalid
  pattern is an error, negated or not), `assertNotContains` to
  `Assert::iterable($h)->notContains()` (`===`, like the flat `assertContains` conversion; PHPUnit's
  `SplObjectStorage` key lookup gives the same verdict as iterating its objects),
  `assertObjectHasProperty` to `Assert::object($o)->hasProperty()` (`property_exists()` and PHPUnit's
  `ReflectionObject::hasProperty()` agree on private, inherited, static and dynamic properties),
  `assertIsList` to `Assert::array($a)->isList()`, `assertContainsOnlyArray`/`Bool`/`Float`/`Int`/
  `Null`/`String` to `Assert::iterable($h)->allOf('int')` and so on (both pass an empty haystack),
  `assertContainsOnlyInstancesOf($c, $h)` to `Assert::iterable($h)->allInstanceOf($c)` (`instanceof`,
  an unknown class throws on both sides), `assertSameSize` to
  `Assert::iterable($a)->sameSizeAs($e)` when both sides are arrays or `Countable` iterables,
  `assertJson` to `Assert::json($s)` (both reject an empty string and unparseable JSON), and
  `assertIsString`/`Int`/`Float`/`Numeric`/`Array`/`Iterable`/`Object`/`Bool`/`Callable` to the type
  head of the same name (with a message, to `Assert::true(\is_string($x), $message)`, since a head
  takes none; both sides run `is_callable()` outside the test's scope, so a private method fails on
  both). The checks with no Testo matcher (`assertIsScalar`, the
  `assertIsNot*` family, `assertFileExists`, `assertDirectoryExists`, `assertIsReadable`,
  `assertIsWritable` and their negations) become `Assert::true|false(\is_bool($x))` and so on,
  running PHPUnit's own predicate. `assertFileIsReadable`/`Writable` become
  `Assert::true(\is_readable($f))`/`\is_writable()` (both fail for a missing path, which covers
  PHPUnit's existence check); the negated file checks and the `assertDirectoryIs*` permission checks,
  which PHPUnit runs as an existence assertion plus a permission one, become
  `Assert::true(\file_exists($f) && !\is_readable($f))` / `\is_dir($d) && \is_writable($d)` and so on.
  `assertFinite`/`Infinite`/`Nan` become `Assert::true(\is_finite($x))` and so on for an `int|float`
  subject. `assertIsResource`/`IsNotResource` become `Assert::true|false(\str_starts_with(\gettype($x),
  'resource'))` and `assertIsClosedResource`/`IsNotClosedResource` `Assert::same|notSame(\gettype($x),
  'resource (closed)')`: PHPUnit counts a closed resource as a resource, which `is_resource()` does
  not. `assertNotInstanceOf(Foo::class, $x)` becomes `Assert::false($x instanceof Foo)` for an existing
  class or interface, and `assertObjectNotHasProperty($p, $o)` becomes
  `Assert::false(\property_exists($o, $p))` for an object subject. Same "only inside a class" gate as
  `AssertCallToTestoRector`. **Message residual (by design):** the numeric matchers,
  `sameElementsAs()`, `blank()`/`notBlank()` all keep a trailing `$message` — but the array-key
  matchers (`hasKeys`/`doesNotHaveKeys`) are variadic with no message parameter, so a PHPUnit message
  on `assertArrayHasKey`/`assertArrayNotHasKey` is dropped (mirrors the reverse direction, which emits
  keyed assertions without a message). `Assert::json()` takes no message either and `json_validate()`
  needs PHP 8.3, so a message on `assertJson` is dropped too. `assertNotEqualsCanonicalizing` has no counterpart (there is no
  `notSameElementsAs`) and is left untouched.

  **Pattern residual:** PHPUnit 9's `assertRegExp`/`assertNotRegExp` ran `preg_match() > 0` with no
  error check, so a match that fails at runtime (backtrack or JIT stack limit) failed `assertRegExp`
  and passed `assertNotRegExp`; `matchesRegex()`/`notMatchesRegex()` throw
  `\InvalidArgumentException` there, as PHPUnit 10+ throws a framework exception. The string matchers
  also need a string subject: outside `strict_types` PHPUnit's `string` parameter coerces an int,
  float or `Stringable`, which `Assert::string()` rejects.

  **Whitespace residual:** PHPUnit runs `preg_replace('/\s+/u', ' ')` and `trim()`, while
  `ignoringWhitespace(lineBreaks: true)` also counts the `\h`/`\v` characters outside `\s` (U+180E)
  and trims spaces only, so a NUL at either end is trimmed by PHPUnit alone. On invalid UTF-8
  PHPUnit's `preg_replace()` fails, while Testo falls back to ASCII whitespace.

  **Assertions left untouched (no matcher gives PHPUnit's verdict):**
  - `assertEqualsIgnoringCase`/`NotEqualsIgnoringCase` with a side not known to be a string: PHPUnit
    also compares other scalars (as strings when one side is a string) and arrays, recursively.
  - The file variants (`assertFileEqualsIgnoringCase`, `assertStringEqualsFileIgnoringCase`,
    `assertStringEqualsFileIgnoringWhitespace`, …): the file has to be read first, which a
    one-statement rewrite does not do.
  - `assertContainsOnlyObject`/`Callable`/`Iterable`/`Numeric`/`Scalar`/`Resource`/`ClosedResource`:
    `allOf()` compares `get_debug_type()`, which reports an object by its class and a resource as
    `resource (stream)`, and knows no pseudo-type.
  - `assertContainsNotOnly*`: there is no negated `allOf()`/`allInstanceOf()`.
  - `assertSameSize` with a side that is not an array or a `Countable` iterable: PHPUnit also counts a
    non-iterable `Countable` (which `sameSizeAs()` does not accept) and throws for a `Traversable`
    that yields a `Generator` (which Testo counts). `assertNotSameSize` has no matcher.
  - The negated file checks and the directory permission checks with a path that is not a variable,
    literal, constant or a concatenation of those: the converted check reads the path twice.
  - `assertFinite`/`Infinite`/`Nan` on a subject not known to be `int|float`: PHPUnit fails any other
    type, while `is_finite()` coerces a numeric string (or throws under `strict_types`).
  - `assertNotInstanceOf` with a dynamic or relative class name, an unknown class or a trait: PHPUnit throws for a name that is neither a class nor an interface, where `instanceof` is
    simply false.
  - `assertObjectNotHasProperty` on a subject not known to be an object: `property_exists()` also
    accepts a class-name string, which PHPUnit's `object` parameter rejects.
  - `assertJsonStringEqualsJsonString`/`NotEquals`, `assertJsonStringEqualsJsonFile`/`NotEquals` and
    `assertJsonFileEqualsJsonFile`/`NotEquals`: PHPUnit decodes to objects, sorts object keys
    recursively and compares the re-encoded strings, so `1` differs from `1.0`, `"1"` from `1` and `{}`
    from `[]`, while list order counts. `Assert::equals()` over `json_decode(..., true)` conflates all
    three pairs, and no Testo matcher compares canonical JSON.
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
