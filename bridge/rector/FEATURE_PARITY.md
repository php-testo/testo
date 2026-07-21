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
| **Basic assertions** (same/equals/true/false/null/count/contains/instanceOf/fail) | ✅ *`AssertCallToPhpUnitRector`; only inside a class — `$this->assert*` in instance scope, `self::assert*` where `$this` is unavailable (static helper/data provider); a call in a free function or at namespace level is left untouched (no valid `$this`/`self::` target)* | ✅ *`AssertCallToTestoRector`; restores arg order; also inside a class only (a test method / static data provider), a call elsewhere is left untouched* | 🟡 *(`expect()->toX()`, 8 matchers, single only)* |
| **actual/expected argument swap** | ✅ | ✅ | ✅ |
| **Fluent / typed chains** (`Assert::string()->…`, Pest `->not->`, `toBeGreaterThan`) | 🟡 *`TypedAssertChainRector` decomposes into separate `assert*` lines (incl. 1→N `hasKeys`, `between`, `isList`→`assertIsList`); a non-variable subject is hoisted into a `$value` local so it is evaluated once; only inside a class (the emitted `$this->assert*` needs a method scope); JSON path/structure, `every`, custom matchers are left untouched + TODO* | ⛔ *reverse means **coalescing** independent statements into one chain — impractical* | 🧩 *function host now exists; `ExpectToAssertRector` still leaves negated `->not->` and chained `->toX()->toY()` expectations untouched — mappable next* |
| **Exception expectation (bare)** | ✅ *bare `\Testo\Expect::exception($c)` → `$this->expectException($c)` (`ExpectExceptionToPhpUnitRector`); the attribute form `#[\Testo\Assert\ExpectException($c)]` → prepended `$this->expectException($c)` (`ExpectExceptionAttributeToPhpUnitRector`)* | 🟡 | ✅ *`TestCallToFunctionRector` folds `->throws(X::class)` into a prepended `\Testo\Expect::exception(X)` + `never` return type* |
| **Exception message/code (fluent)** `withMessage/withCode` ↔ `expectExceptionMessage/Code` | ✅ *`ExpectExceptionToPhpUnitRector` expands one chain into several statements (`withMessage`→`expectExceptionMessage`, `withCode`→`expectExceptionCode`, regex `withMessagePattern`→`expectExceptionMessageMatches`); substring `withMessageContaining` aborts the chain (no faithful PCRE target)* | ✅ *`ExpectExceptionToTestoRector` folds an uninterrupted run of sibling `expectExceptionMessage/Code` after `expectException` into the `->withMessage()/->withCode()` chain (StmtsAware); a non-foldable call ends the run* | 🟡 *`->throws(X, 'msg')`'s second arg folds to `->withMessage('msg')`; Pest has no exception-code modifier to map* |
| **Exception message by regex** (`expectExceptionMessageMatches`) | ➖ | ⛔ *Testo's `withMessageContaining` is substring, not regex* | ➖ |
| **Skip** (`throw SkipTest` ↔ `markTestSkipped`) | ✅ | ✅ | 🟡 *`->skip('reason')` → prepended `throw new \Testo\Core\Exception\SkipTest('reason')`; a conditional `->skip(fn () => …)` is left untouched* |
| **Incomplete** (`markTestIncomplete`) | ➖ | 🟡 *`MarkTestIncompleteRector`: Testo has no Incomplete status, so it maps to the nearest one — a `throw new \Testo\Core\Exception\SkipTest(...)` (Skipped). Lossy: the Incomplete-vs-Skipped nuance survives only as an `Incomplete: ` prefix on the reason (literal folds to `'Incomplete: <msg>'`, non-literal to `'Incomplete: ' . $expr`, bare call to `'Incomplete'`)* | ➖ |
| **Cancel** (`CancelTest`) | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Coverage attribute** (`#[Covers]` ↔ `#[CoversClass]`) | ✅ | ✅ | ✅ *`->covers(X::class)` → `#[\Testo\Codecov\Covers(X::class)]`* |
| **Lifecycle hooks** | ✅ *attribute → attribute; plus `ConstructorDestructorToLifecycleRector` turns a parameterless `__construct()`/`__destruct()` into a renamed `#[Before]`/`#[After]` method (coexists with any existing `setUp`)* | ✅ *(`setUp/tearDown` → attributes)* | 🟡 *`beforeEach/afterEach/beforeAll/afterAll` → functions carrying `Testo\Lifecycle\{BeforeTest,AfterTest,BeforeClass,AfterClass}`; relies on Testo's function-level lifecycle, and `$this`-shared state is left manual* |
| **Class / method structure** (base class, `#[Test]`, discovery) | 🟡 *`TestClassToTestCaseRector` adds `extends TestCase` and converts `#[\Testo\Test]` (class-level → per-method `#[Test]` on public void/never methods, EXCLUDING lifecycle hooks — `setUp`/`tearDown` names or `#[Before]`-family attributes; method-level → attribute rename, and a `static` test method is made an instance method since PHPUnit flags a static `#[Test]`); only classes that extend nothing; no method rename* | 🟡 *`ExtendsTestCaseToTestoRector` removes a **direct** `extends \PHPUnit\Framework\TestCase` and marks each test method (`#[Test]` / `@test` / `test`-prefix) with `#[\Testo\Test]`; intermediate/custom bases left untouched; no method rename* | ✅ *`TestCallToFunctionRector` turns each `test()/it()` into a `#[\Testo\Test]` **free function** (no host class needed — the old core blocker); name = `test_`/`it_` + snake(description), description kept verbatim as the docblock* |
| **Data providers** (`#[DataProvider]`/`#[DataSet]` ↔ `->with`) | ✅ *`DataProviderToPhpUnitRector` renames `#[\Testo\Data\DataProvider]` → `#[DataProvider]` and `#[\Testo\Data\DataSet([…], 'label')]` → `#[TestWith([…], 'label')]` (both repeatable, args verbatim)* | ✅ *both `@dataProvider` annotation **and** `#[DataProvider]` attribute → `#[\Testo\Data\DataProvider]`; cross-class external form left as TODO* | 🟡 *inline `->with([ rows ])` → one repeated `#[\Testo\Data\DataSet]` per row; a named `->with('x')` / `dataset()` definition needs a provider — TODO* |
| **Groups** (`#[Group]`) | ✅ *`GroupToPhpUnitRector` expands variadic → repeated `#[Group]`; `GroupInheritanceToPhpUnitRector` flattens both the class-level inheritance union (parents + traits) and the method-level prototype chain (a leaf method inherits the groups of the same-named parent-class method). Residual: traits are intentionally not consulted at method level — matches Testo, whose prototype walk skips them* | ✅ *`GroupToTestoRector` collapses `@group` annotations **and** repeated `#[Group]` into one variadic `#[\Testo\Filter\Group]`* | ✅ *`->group('a','b')` → `#[\Testo\Filter\Group('a','b')]`* |
| **ExpectNoAssertions** (`#[\Testo\Assert\ExpectNoAssertions]` ↔ `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`) | ✅ *`ExpectNoAssertionsToPhpUnitRector` (attribute rename; both sides method/function-level only — no fan-out)* | ✅ *`DoesNotPerformAssertionsToTestoRector` (attribute rename)* | ➖ |
| **Mocks** (`createMock`/`getMockBuilder`/`prophesize`) | ➖ | ⛔ *Testo has no built-in mocking* | ➖ |
| **Memory-leak expectations** | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Retry / Repeat** (`#[Retry]`/`#[Repeat]`) | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Fiber** (`#[RunInFiber]`) | ⛔ *no PHPUnit/Pest equivalent — neither has a fiber/coroutine test attribute* | ➖ | ➖ |
| **`uses()`** (Pest) | ➖ | ➖ | ⛔ *a converted function has no base class, traits or `$this` to attach to; closures that capture `$this`-shared state are left untouched* |
| **`arch()` tests** (Pest) | ➖ | ➖ | ⛔ *Testo has no arch-assertion subsystem* |

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
the attribute (idempotent — a method already carrying `#[\Testo\Test]` is left alone). The mirror
`TestClassToTestCaseRector` (Testo → PHPUnit) adds `extends \PHPUnit\Framework\TestCase` and, for a
class-level `#[\Testo\Test]`, removes it and adds a per-method `#[\PHPUnit\Framework\Attributes\Test]`
to every public, non-static, `void`/`never` method (mirroring Testo's locator, so static/`iterable`
providers are skipped); a method-level `#[\Testo\Test]` is renamed to the PHPUnit attribute.
**Residuals (both rules):** methods are NOT renamed — Testo discovers by attribute regardless of
name, and a converted-to-PHPUnit class relies on the emitted `#[Test]` attribute rather than a `test`
prefix, so call-site cleanup is left manual. Scope is deliberately narrow: PHPUnit → Testo only fires
on a class that extends `TestCase` *directly* (intermediate/custom bases are left for conversion at
the base), and Testo → PHPUnit only fires on a class that extends *nothing* (a class already
extending a base is left untouched to avoid a single-inheritance clash).

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
several `$this->expect*` statements (`Node[]` return), and `ExpectExceptionToTestoRector` folds an
uninterrupted run of sibling `expectExceptionMessage/Code` calls back into the
`\Testo\Expect::exception(...)->withMessage()->withCode()` chain (operating on the StmtsAware
node's `->stmts`). **Residual:** Testo→PHPUnit cannot map the substring matcher
`withMessageContaining` (PHPUnit's only message matcher is the PCRE `expectExceptionMessageMatches`),
so a chain using it is left untouched rather than silently mistranslated — the same substring-vs-regex
mismatch that blocks the reverse `ExpectExceptionMessageMatchesRector`.

Also done: **Pest functional → Testo functions** — `TestCallToFunctionRector` (with
`ExpectToAssertRector` for the assertion bodies) restructures a Pest file's `test()/it()` and
lifecycle calls into attribute-bearing free functions, deriving a deterministic `test_`/`it_`
name from the description (kept as the docblock) and folding the fluent modifier chain into
attributes / body statements. It bails (leaves the statement untouched) on a non-literal description,
a `use (...)`-capturing closure, or any unrecognised modifier — see `src/PestToTesto/TODO.md`.

The remaining ⛔ rows are intentionally out of scope: a missing target feature (mocking, `arch()`,
memory-leak, retry/repeat, PHPUnit `assertThat` constraints), the substring-vs-regex
exception-message mismatch, or Pest `uses()` (a function has no base class / traits / `$this`).
PHPUnit's `markTestIncomplete` moved off this list — it now converts to a Skipped throw with an
`Incomplete: ` reason prefix (`MarkTestIncompleteRector`), a documented lossy 🟡 rather than a ⛔.
