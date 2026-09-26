# Feature parity

Conversion coverage across the three directions supported by `testo/bridge-rector`.

**Legend**

- ✅ **Done** — rule + fixtures, passing.
- 🟡 **Partial** — common case works; documented gap remains.
- 🧩 **Tractable, not done yet** — a concrete gap worth filling.
- ⛔ **Blocked / impossible** — documented stub; either the target framework has no faithful
  feature, or Rector cannot perform the required restructuring.
- ➖ **N/A** — the source framework has no such concept for this direction.

| Feature / concept | Testo → PHPUnit | PHPUnit → Testo | Pest → Testo |
|---|:---:|:---:|:---:|
| **Basic assertions** (same/equals/true/false/bool/null/count/contains/instanceOf/fail) | ✅ *`AssertCallToPhpUnitRector` (`Assert::bool()`→`assertIsBool`); only inside a class — `$this->assert*` in instance scope, `self::assert*` where `$this` is unavailable (static helper/data provider); a call in a free function or at namespace level is left untouched (no valid `$this`/`self::` target)* | ✅ *`AssertCallToTestoRector`; restores arg order; `assertNotTrue`/`assertNotFalse`→`Assert::notSame($x, true|false)`; the `PHPUnit\Framework\assert*()` functions convert too; also inside a class only (a test method / static data provider), a call elsewhere is left untouched* | 🟡 *(`expect()->toX()`, 8 matchers, single only)* |
| **actual/expected argument swap** | ✅ | ✅ | ✅ |
| **Fluent / typed chains** (`Assert::string()->…`, Pest `->not->`, `toBeGreaterThan`) | 🟡 *`TypedAssertChainRector` decomposes into separate `assert*` lines (incl. 1→N `hasKeys`, `between`, `isList`→`assertIsList`, `sameElementsAs`→`assertEqualsCanonicalizing`, the `iterable` head→`assertIsIterable`, a bare head such as `Assert::callable($x)`→`assertIsCallable`, `allInstanceOf`→`assertContainsOnlyInstancesOf`, `allOf('int')`→`assertContainsOnlyInt` for the exact native types, `sameSizeAs`→`assertSameSize` on countable sides, `matchesRegex`/`notMatchesRegex`→`assertMatchesRegularExpression`/`assertDoesNotMatchRegularExpression`, `ignoringCase()->contains()`/`notContains()`→`assertStringContainsStringIgnoringCase`/`assertStringNotContainsStringIgnoringCase`, `ignoringLineEndings()->contains()`→`assertStringContainsStringIgnoringLineEndings`, `same`/`notSame`→`assertSame`/`assertNotSame`, `notStartsWith`/`notEndsWith`→`assertStringStartsNotWith`/`assertStringEndsNotWith`, `ignoringCase()->same()`/`notSame()`→`assertEqualsIgnoringCase`/`assertNotEqualsIgnoringCase`, `ignoringLineEndings()->same()`→`assertStringEqualsStringIgnoringLineEndings`, `ignoringWhitespace(true)->same()`/`notSame()`→`assertStringEqualsStringIgnoringWhitespace`/`NotEquals`); a non-variable subject is hoisted into a `$value` local so it is evaluated once; only inside a class (the emitted `$this->assert*` needs a method scope); JSON path/structure, `every`, the `callable` matchers (`isStatic`/`notStatic`, `hasReturnType`), custom matchers, and the string modifier combinations PHPUnit lacks (`ignoringCase()->startsWith()`, several modes together, `ignoringWhitespace()` without line breaks, `ignoringBlankLines()`, `ignoringAnsi()`) are left untouched + TODO* | 🟡 *`TypedAssertCallToTestoRector` converts the assertions whose faithful Testo form is a typed head + matcher: comparisons (`assertGreaterThan`→`Assert::numeric()->greaterThan()`, …), array keys (`assertArrayHasKey`→`Assert::array()->hasKeys()`), `assertEqualsCanonicalizing`→`sameElementsAs`, array-subject `assertEmpty`/`assertNotEmpty`→`blank`/`notBlank` (a non-object subject → `Assert::true(empty($x))`), `assertStringStartsWith`/`EndsWith`→`Assert::string()->startsWith()`/`endsWith()`, `assertStringStartsNotWith`/`EndsNotWith`→`notStartsWith()`/`notEndsWith()`, `assertStringContainsString`/`NotContainsString`→`Assert::string()->contains()`/`notContains()`, `assertStringContainsStringIgnoringCase`/`NotContainsStringIgnoringCase`→`Assert::string()->ignoringCase()->contains()`/`notContains()`, `assertStringContainsStringIgnoringLineEndings`→`Assert::string()->ignoringLineEndings()->contains()`, `assertStringEqualsStringIgnoringLineEndings`→`->ignoringLineEndings()->same()`, `assertStringEqualsStringIgnoringWhitespace`/`NotEquals`→`->ignoringWhitespace(lineBreaks: true)->same()`/`notSame()`, `assertEqualsIgnoringCase`/`NotEqualsIgnoringCase`→`->ignoringCase()->same()`/`notSame()` (both sides strings), `assertMatchesRegularExpression`/`DoesNotMatchRegularExpression` (and the PHPUnit 9 `assertRegExp`/`assertNotRegExp`)→`Assert::string()->matchesRegex()`/`notMatchesRegex()`, `assertNotContains`→`Assert::iterable()->notContains()`, `assertObjectHasProperty`→`Assert::object()->hasProperty()`, `assertIsList`→`Assert::array()->isList()`, `assertContainsOnlyInt`/`String`/…→`Assert::iterable()->allOf('int')` and `assertContainsOnlyInstancesOf`→`allInstanceOf()`, `assertSameSize`→`Assert::iterable()->sameSizeAs()` (`Countable` sides), `assertJson`→`Assert::json()` (JSON equality stays: PHPUnit compares canonical re-encoded JSON), `assertIs<Type>`→the type head (`assertIsCallable`→`Assert::callable()` included; `assertIsBool`→the flat `Assert::bool()`), and the predicate checks (`assertIsScalar`, `assertIsNot*`, `assertFileExists`, `assertDirectoryExists`, …)→`Assert::true|false(\is_bool($x))`, also the file/directory permission checks (`\file_exists($f) && !\is_readable($f)`, side-effect-free path), `assertFinite`/`Infinite`/`Nan` (`int|float` subject), the resource checks over `\gettype()` (a closed resource counts), `assertNotInstanceOf`→`Assert::false($x instanceof Foo)` and `assertObjectNotHasProperty`→`Assert::false(\property_exists($o, $p))` (object subject). Each is a 1:1 statement rewrite; `MergeAssertChainRector` then folds adjacent same-head chains that open with the same comparison modifiers. General coalescing of arbitrary independent `assert*` lines remains impractical* | 🧩 *function host now exists; `ExpectToAssertRector` still leaves negated `->not->` and chained `->toX()->toY()` expectations untouched — mappable next* |
| **Exception expectation (bare)** | ✅ *bare `\Testo\Expect::exception($c)` → `$this->expectException($c)` (`ExpectExceptionToPhpUnitRector`); the attribute form `#[\Testo\Assert\ExpectException($c)]` → prepended `$this->expectException($c)` (`ExpectExceptionAttributeToPhpUnitRector`)* | 🟡 | ✅ *`TestCallToFunctionRector` folds `->throws(X::class)` into a prepended `\Testo\Expect::exception(X)` + `never` return type* |
| **Exception message/code (fluent)** `withMessage/withCode` ↔ `expectExceptionMessage/Code` | ✅ *`ExpectExceptionToPhpUnitRector` expands one chain into several statements (`withMessageContaining`→`expectExceptionMessage`, `withCode`→`expectExceptionCode`, regex `withMessageMatchingRegex` (and the deprecated `withMessagePattern`)→`expectExceptionMessageMatches`); the exact `withMessage` also maps to `expectExceptionMessage` and loosens into a substring check; two modifiers on the same PHPUnit call abort the chain* | ✅ *`ExpectExceptionToTestoRector` folds the sibling `expectExceptionMessage/Code` after `expectException` into the `->withMessageContaining()/->withCode()` chain (StmtsAware), across assignments that cannot throw — PHPUnit matches the message as a substring; any other statement ends the run* | 🟡 *`->throws(X, 'msg')`'s second arg folds to `->withMessageContaining('msg')`; Pest has no exception-code modifier to map* |
| **Exception message by regex** (`expectExceptionMessageMatches`) | ✅ *`withMessageMatchingRegex`/`withMessagePattern`→`expectExceptionMessageMatches`* | ✅ *`ExpectExceptionToTestoRector` folds it into `->withMessageMatchingRegex()`* | ➖ |
| **Skip at runtime** (`throw SkipTest` ↔ `markTestSkipped`) | ✅ | ✅ *`MarkTestSkippedToTestoRector` takes what the attribute form below leaves: a guarded call, a call deeper in the body, a non-literal message* | ➖ *every Pest `->skip()` is unconditional — see the row below* |
| **Skip declaratively** (`#[Skip]`) | ✅ *`SkipAttributeToPhpUnitRector`: PHPUnit has no skip attribute, so `#[\Testo\Skip('reason')]` becomes a leading `$this->markTestSkipped('reason')` and the attribute is dropped; a class-level attribute is fanned out onto each test method (a method's own reason wins). **Residual:** the skip turns into a runtime one — PHPUnit runs `setUp()` and the data provider before aborting, where Testo keeps the test out of the pipeline entirely* | ✅ *`MarkTestSkippedToSkipAttributeRector`: an unconditional `markTestSkipped('literal')` opening a test method → `#[\Testo\Skip('literal')]`, dropping the call. A guarded/non-literal one stays a throw (row above); a call opening `setUp()` stays a throw too, rather than becoming a class-level `#[Skip]`* | ✅ *`->skip()` / `->skip('reason')` → `#[\Testo\Skip('reason')]`; a conditional `->skip(fn () => …)` / `->skip($bool, 'reason')` leaves the whole statement untouched* |
| **Incomplete** (`markTestIncomplete`) | ➖ | 🟡 *`MarkTestIncompleteRector`: Testo has no Incomplete status, so it maps to the nearest one — a `throw new \Testo\Core\Exception\SkipTest(...)` (Skipped). Lossy: the Incomplete-vs-Skipped nuance survives only as an `Incomplete: ` prefix on the reason (literal folds to `'Incomplete: <msg>'`, non-literal to `'Incomplete: ' . $expr`, bare call to `'Incomplete'`)* | ➖ |
| **Cancel** (`CancelTest`) | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Coverage attribute** (`#[Covers]` ↔ `#[CoversClass]`) | ✅ *`CoversToCoversClassRector` maps by target kind: a class/enum → `#[CoversClass]`, a trait → `#[CoversTrait]`, an interface is dropped (PHPUnit rejects a non-class coverage target); kind resolved by autoload, an unresolvable name defaults to `#[CoversClass]`* | ✅ | ✅ *`->covers(X::class)` → `#[\Testo\Codecov\Covers(X::class)]`* |
| **Lifecycle hooks** | ✅ *attribute → attribute; plus `ConstructorDestructorToLifecycleRector` turns a parameterless `__construct()`/`__destruct()` into a renamed `#[Before]`/`#[After]` method (coexists with any existing `setUp`)* | ✅ *(`setUp/tearDown` → attributes)* | 🟡 *`beforeEach/afterEach/beforeAll/afterAll` → functions carrying `Testo\Lifecycle\{BeforeTest,AfterTest,BeforeClass,AfterClass}`; relies on Testo's function-level lifecycle, and `$this`-shared state is left manual* |
| **Class / method structure** (base class, `#[Test]`, discovery) | 🟡 *`TestClassToTestCaseRector` adds `extends TestCase` and converts `#[\Testo\Test]` (class-level → per-method `#[Test]` on public void/never methods, EXCLUDING lifecycle hooks — `setUp`/`tearDown` names or `#[Before]`-family attributes; method-level → attribute rename, and a `static` test method is made an instance method since PHPUnit flags a static `#[Test]`); only classes that extend nothing; no method rename* | 🟡 *`ExtendsTestCaseToTestoRector` removes a **direct** `extends \PHPUnit\Framework\TestCase` and marks each test method (`#[Test]` / `@test` / `test`-prefix) with `#[\Testo\Test]`; a class reaching `TestCase` through an intermediate base keeps its `extends` and only gains the `#[\Testo\Test]` marks; no method rename* | ✅ *`TestCallToFunctionRector` turns each `test()/it()` into a `#[\Testo\Test]` **free function** (no host class needed — the old core blocker); name = `test_`/`it_` + snake(description), description kept verbatim as the docblock* |
| **Data providers** (`#[DataProvider]`/`#[DataSet]` ↔ `->with`) | ✅ *`DataProviderToPhpUnitRector` renames `#[\Testo\Data\DataProvider]` → `#[DataProvider]` and `#[\Testo\Data\DataSet([…], 'label')]` → `#[TestWith([…], 'label')]` (both repeatable, args verbatim)* | ✅ *both `@dataProvider` annotation **and** `#[DataProvider]` attribute → `#[\Testo\Data\DataProvider]`; cross-class external form left as TODO* | 🟡 *inline `->with([ rows ])` → one repeated `#[\Testo\Data\DataSet]` per row; a named `->with('x')` / `dataset()` definition needs a provider — TODO* |
| **Groups** (`#[Group]`) | ✅ *`GroupToPhpUnitRector` expands variadic → repeated `#[Group]`; `GroupInheritanceToPhpUnitRector` flattens both the class-level inheritance union (parents + traits) and the method-level prototype chain (a leaf method inherits the groups of the same-named parent-class method). Residual: traits are intentionally not consulted at method level — matches Testo, whose prototype walk skips them* | ✅ *`GroupToTestoRector` collapses `@group` annotations **and** repeated `#[Group]` into one variadic `#[\Testo\Filter\Group]`* | ✅ *`->group('a','b')` → `#[\Testo\Filter\Group('a','b')]`* |
| **ExpectNoAssertions** (`#[\Testo\Assert\ExpectNoAssertions]` ↔ `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`) | ✅ *`ExpectNoAssertionsToPhpUnitRector` (attribute rename; both sides method/function-level only — no fan-out)* | ✅ *`DoesNotPerformAssertionsToTestoRector` (attribute rename)* | ➖ |
| **Mocks** (`createMock`/`createStub` + `expects`/`method`/`will*`/`with`) | ➖ | ➖ *not part of this set: Testo core ships no mocking, so doubles convert through the mock sets below (Double or Mockery)* | ➖ |
| **Memory-leak expectations** | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Retry / Repeat** (`#[Retry]`/`#[Repeat]`) | 🟡 *`RepeatRetryRector` converts `#[\Testo\Repeat]`/`#[\Testo\Retry]` → PHPUnit `#[Repeat]`/`#[Retry]` (PHPUnit 13.3+): `maxFailures`→`failureThreshold` (+1), Testo defaults made explicit. PHPUnit's are `TARGET_METHOD` only, so a class-level Testo attribute is fanned out onto each test method (a method's own attribute overrides it, not doubled); `markFlaky` is dropped (no PHPUnit equivalent)* | 🟡 *`RepeatRetryToTestoRector` converts `#[Repeat]`/`#[Retry]` → Testo's attributes: `failureThreshold`→`maxFailures` (−1; the default 1 folds to Testo's default 0 and is omitted)* | ➖ |
| **Fiber** (`#[RunInFiber]`, `Coroutine::spawn/await/concurrently`) | ⛔ *no PHPUnit/Pest equivalent — neither has a fiber/coroutine test attribute or an in-test coroutine scope* | ➖ | ➖ |
| **HTML report** (`HtmlPlugin`, `--log-html`) | ⛔ *not test code — a reporter configured in `testo.php` or by a flag, with nothing in a test file to convert* | ➖ | ➖ |
| **`uses()`** (Pest) | ➖ | ➖ | ⛔ *a converted function has no base class, traits or `$this` to attach to; closures that capture `$this`-shared state are left untouched* |
| **`arch()` tests** (Pest) | ➖ | ➖ | ⛔ *Testo has no arch-assertion subsystem* |

## Mock sets

Test doubles convert through their own sets, one per target library (`PHPUNIT_TO_DOUBLE`,
`PHPUNIT_TO_MOCKERY`, `MOCKERY_TO_DOUBLE`). Each rule rewrites a configuration statement whole or
leaves it untouched; the per-folder `TODO.md` lists the residuals.

| Feature / concept | PHPUnit → Double | PHPUnit → Mockery | Mockery → Double |
|---|:---:|:---:|:---:|
| **Creation** | 🟡 *`CreateMockToDoubleRector`: `createMock`/`createStub`/intersections/`disableOriginalConstructor()` builder → `Double::for`, `disableAutoReturnValueGeneration` → `->strict()`; `createConfiguredMock` → `Double::for` + `allows()->returns()` per entry; `createPartialMock`/`onlyMethods` → `->passthru()` (+ `passthru(new X(...))` when the constructor runs) + `allows()` per doubled method. Left: `prophesize`, `getMockForAbstractClass`, `addMethods`, the bare constructor-calling builder, strict partials* | 🟡 *`CreateMockToMockeryRector`: the same forms → `\Mockery::mock(...)->shouldIgnoreMissing()`, configured → quick definitions, partial → `\Mockery::mock('X[a]', $ctorArgs)`, all-real partial → `->makePartial()`. Same residuals* | 🟡 *`MockeryToDoubleRector`: `mock` → `->strict()`, `spy`/`shouldIgnoreMissing` → loose, `makePartial` → `->passthru()` (with ctor args → `passthru(new X(...))`), `'A, B'`/several targets → intersection, typed computed targets, `mock($object)` → proxied passthru, quick definitions and inline `->getMock()` split into statements. Left: bare `mock()`, full mocks with ctor args, `alias:`/`overload:`/`X[m]`* |
| **Call counts** | ✅ *matcher → `times()`/`never()`, `any` → `allows`* | ✅ *matcher → `once`/`times`/`never`/`atLeast()`/`atMost()`* | ✅ *`once` (the `expects` default), `twice`/`times`, `never`, `atLeast()`/`atMost()`, `between`, `zeroOrMoreTimes`, `shouldNotReceive`; `ordered()` kept, `globally()`/groups left manual* |
| **Returns** | ✅ *`willReturn*`, `willThrowException`, `willReturnCallback`/`Argument`/`Self`/`Map`, legacy `will(...)`; `willReturnReference` left manual* | ✅ *same set → `andReturn*`/`andThrow`/`andReturnUsing(<map lookup>)`* | ✅ *`andReturn(s)`/`Values` (literal or array-typed)/`Null`/`True`/`False`/`Self`/`Arg`/`Using`, `andThrow(s)` (object or class-string), `andThrowExceptions`; `andReturnUndefined`/`andSet`/`passthru` left manual* |
| **Argument matching** | ✅ *dedicated `Argument::*` matchers, every other constraint (incl. `logicalAnd`/`Xor`, delta/case/canonicalizing equality, `countOf`, `isJson`, `containsOnly*`, PHPUnit 12 type factories) → `satisfies()` over its PHP expression, verdicts pinned against PHPUnit in `PhpunitConstraintTest`; `with()` gains a trailing `Argument::remaining()` since PHPUnit ignores extra call arguments and Double does not, an empty `with()` drops. Left: computed case flag / type, `matches()` formats* | ✅ *dedicated Mockery matchers, the rest → `Mockery::on()` over the same expressions; `with()` gains a trailing `\Mockery::andAnyOtherArgs()` for the same reason, an empty `with()` drops* | 🟡 *`with`/`withArgs` (literal, array-typed, closure)/`withNoArgs`/`withAnyArgs`/`withSomeOfArgs`, magic `allows()->m($x)`, Mockery matchers → `Argument::*` per the migration table; `hasKey`/multi-`contains`/`subset`/`ducktype` → `satisfies()`. Left: nested matchers, Hamcrest. Residual by design: Mockery compares loosely, Double strictly* |
| **Several expectations in one call** | ➖ | ➖ | ✅ *`shouldReceive('a', 'b')`, `shouldReceive(['a' => 1])`, `allows(['a' => 1])` → one statement per method (link arguments must be side-effect free)* |
| **Default expectations** (`byDefault`) | ➖ | ➖ | 🟡 *dropped when the expectation has no arguments and no count (Double tries the newest expectation first); otherwise left manual* |
| **Spy verification** | ➖ | ➖ | ✅ *`shouldHaveReceived`/`shouldNotHaveReceived` (plain, array-args, closure and magic forms) → `received()`/`->never()`, `shouldHaveBeenCalled`/`shouldNotHaveBeenCalled` → `received('__invoke')`; `Mockery::close()` → `Double::verifyAll()`* |
| **PHPUnit integration** | ➖ | ➖ | ✅ *`MockeryIntegrationToDoubleRector`: `MockeryPHPUnitIntegration` → `VerifiesDoubles`, `extends MockeryTestCase` → `extends TestCase` + the trait* |

## Polish set (Testo → Testo)

`TESTO_POLISH` has no conversion direction to compare: each rule rewrites Testo into equivalent, tidier Testo. The PHPUnit → Testo direction already emits method-level `#[Test]` and `Expect::exception()`; the polish set is where they turn into the class-level and attribute forms.

| Feature / concept | Testo → Testo |
|---|:---:|
| **Class-level `#[Test]`** | ✅ *`ClassLevelTestAttributeRector`: only on a `final` class whose public `void`/`never` and untyped methods are all tests or lifecycle hooks, inherited and trait methods included (read by reflection); a class already carrying `#[Test]` loses the redundant method attributes, and so does a trait whose users within the processed paths are all class-level (seen from the next run on)* |
| **`#[ExpectException]`** | ✅ *`ExpectExceptionToAttributeRector`: a test whose body is exactly a bare `Expect::exception(X::class)` and one statement. Modifiers, a specimen object, `same: true` or statements before the call keep the method form* |
| **`final` test classes** | ✅ *`FinalizeTestClassRector`: `*Test` classes carrying `#[Test]`, not `*TestCase`, not abstract, not extended within the processed paths* |
| **Return types** | ✅ *Rector's `AddVoidReturnTypeWhereNoReturnRector`, `ReturnNeverTypeRector`* |
| **Assertion pipes** | 🟡 *`MergeAssertChainRector`: adjacent chains with the same typed head on the same variable* |

## Shift set (deprecated Testo → current Testo)

`TESTO_SHIFT` renames deprecated Testo API to its replacement. Each deprecation adds an entry here and to the set.

| Deprecated API | Replacement |
|---|:---:|
| `ExpectedException::withMessagePattern()` | ✅ *`withMessageMatchingRegex()`, Rector's `RenameMethodRector` resolved by type* |

## Remaining work (🧩 — actually tractable)

Pest → Testo opened up once the direction stopped chasing a *class* and targeted **free functions**
instead (Testo discovers file-level `#[\Testo\Test]` functions). With the host-class blocker gone,
`TestCallToFunctionRector` now restructures `test()/it()` and the `beforeEach/afterEach/beforeAll/
afterAll` lifecycle, folding the `->group/->covers/->throws/->skip/->with` chain in the same pass —
so the Pest column flipped from a wall of ⛔ to mostly ✅/🟡. The new 🧩 candidates that this unlocks
(none blocking, all genuinely mappable now that a function host exists):

- **Negated / chained `expect()`** — `ExpectToAssertRector` still leaves `->not->toBe(...)` and
  `->toBe(1)->toBe(2)` untouched; both are now mappable (`Assert::notSame`, statement fan-out).
- **`describe()` blocks** — flatten into prefixed function names + a per-block lifecycle scope.
- **Named datasets** — `dataset('x', …)` + `->with('x')` → a `#[\Testo\Data\DataProvider]` source.

The Testo ↔ PHPUnit directions remain either ✅, a 🟡 with a documented residual, or an intentional ⛔.

Done since the first cut: **structural class/method conversion** (Testo ↔ PHPUnit) —
`ExtendsTestCaseToTestoRector` (PHPUnit → Testo) removes a *direct* `extends
\PHPUnit\Framework\TestCase` and converts each test method to attribute-based discovery: a PHPUnit
`#[\PHPUnit\Framework\Attributes\Test]` attribute is renamed to `#[\Testo\Test]`, a `@test` docblock
annotation is dropped and replaced by the attribute, and a bare `test`-prefixed method simply gains
the attribute (idempotent — a method already carrying `#[\Testo\Test]` is left alone); a trait's
non-abstract test methods gain it the same way. The mirror
`TestClassToTestCaseRector` (Testo → PHPUnit) adds `extends \PHPUnit\Framework\TestCase` and, for a
class-level `#[\Testo\Test]`, removes it and adds a per-method `#[\PHPUnit\Framework\Attributes\Test]`
to every public, non-static, `void`/`never` method (mirroring Testo's locator, so static/`iterable`
providers are skipped); a method-level `#[\Testo\Test]` is renamed to the PHPUnit attribute.
**Residuals (both rules):** methods are NOT renamed — Testo discovers by attribute regardless of
name, and a converted-to-PHPUnit class relies on the emitted `#[Test]` attribute rather than a `test`
prefix, so call-site cleanup is left manual. PHPUnit → Testo detaches only a class that extends
`TestCase` *directly*; a subclass of an intermediate base keeps its `extends` (the base is detached
on its own) and only gains the `#[\Testo\Test]` marks, so its tests stay discoverable. Testo →
PHPUnit only fires on a class that extends *nothing* (a class already extending a base is left
untouched to avoid a single-inheritance clash).

Done since the first cut: **method-level group inheritance** (Testo → PHPUnit) — beyond the
class-level union, `GroupInheritanceToPhpUnitRector` now walks each leaf method's prototype chain (the
same-named method on parent classes, recursively, mirroring `\ReflectionMethod::getPrototype()`) and
appends the parent method's `#[Group]` names onto the leaf method as repeated PHPUnit `#[Group]`
attributes, idempotently. Traits are deliberately excluded at the method level: when a leaf method
overrides a same-named trait method PHP lets the class method win with no prototype, so Testo's own
filter never sees the trait method's groups for that override — the conversion matches that exactly.
Also done: **typed/fluent chain decomposition** (Testo → PHPUnit) now ships as
`TypedAssertChainRector` — `Assert::<type>($v)->m1()->m2()` → `assertIs<Type>($v)` plus one `assert*`
line per matcher (some expand 1→N), with unmapped matchers leaving the chain untouched. The reverse
recomposition is deliberately narrow: `MergeAssertChainRector` (PHPUnit → Testo, cleanup pass) merges
only *adjacent Testo chains that already share an identical typed head* — `Assert::array($l)->hasKeys('a'); Assert::array($l)->isList();`
→ `Assert::array($l)->hasKeys('a')->isList();` — a faithful, no-inference tidy. It does **not** lift the
flat facade calls produced by `AssertCallToTestoRector` (`same`/`true`/`count`/…) into a pipe: those are
`void` or would need a typed head that turns a `TypeError` into an `AssertionException`, so a converted
PHPUnit `assert*` run stays as separate flat lines. Also done: **fluent exception
message/code** in both directions — `ExpectExceptionToPhpUnitRector` expands the Testo chain into
several `$this->expect*` statements (`Node[]` return), and `ExpectExceptionToTestoRector` folds a
run of sibling `expectExceptionMessage/MessageMatches/Code` calls back into the
`\Testo\Expect::exception(...)->withMessageContaining()->withMessageMatchingRegex()->withCode()` chain
(operating on the StmtsAware node's `->stmts`). **Residual:** Testo→PHPUnit maps the exact
`withMessage` onto the substring `expectExceptionMessage`, so that check loosens.

Also done: **Pest functional → Testo functions** — `TestCallToFunctionRector` (with
`ExpectToAssertRector` for the assertion bodies) restructures a Pest file's `test()/it()` and
lifecycle calls into attribute-bearing free functions, deriving a deterministic `test_`/`it_`
name from the description (kept as the docblock) and folding the fluent modifier chain into
attributes / body statements. It bails (leaves the statement untouched) on a non-literal description,
a `use (...)`-capturing closure, or any unrecognised modifier — see `src/PestToTesto/TODO.md`.

The remaining ⛔ rows are intentionally out of scope: a missing target feature (`arch()`,
memory-leak, PHPUnit `assertThat` constraints), the substring-vs-regex
exception-message mismatch, or Pest `uses()` (a function has no base class / traits / `$this`).
Mocks moved off this list: they convert through the dedicated mock sets (see "Mock sets" above).
Retry/Repeat moved off this list: PHPUnit 13.3 added `#[Repeat]`/`#[Retry]`, so both directions now
convert as a documented 🟡 (`RepeatRetryRector` / `RepeatRetryToTestoRector`).
PHPUnit's `markTestIncomplete` moved off this list — it now converts to a Skipped throw with an
`Incomplete: ` reason prefix (`MarkTestIncompleteRector`), a documented lossy 🟡 rather than a ⛔.

Also done: **declarative skip** — Testo's `#[Skip]` attribute (`testo/skip`) splits the skip rows in
two. A skip that states something about the test rather than about a path through it now converts as
an attribute in every direction: `SkipAttributeToPhpUnitRector` unrolls it into the leading
`markTestSkipped()` call PHPUnit needs (fanning a class-level attribute onto each test method), and
both reverse directions produce it — a `markTestSkipped('literal')` opening a test method
(`MarkTestSkippedToSkipAttributeRector`) and Pest's `->skip('reason')` modifier. What stays a
`SkipTest` throw is exactly what cannot be declared: a guarded call, one deeper in the body, or a
message no attribute argument can hold.
