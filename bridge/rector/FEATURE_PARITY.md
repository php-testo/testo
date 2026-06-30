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
| **Basic assertions** (same/equals/true/false/null/count/contains/instanceOf/fail) | ✅ | ✅ *(restores arg order)* | 🟡 *(`expect()->toX()`, 8 matchers, single only)* |
| **actual/expected argument swap** | ✅ | ✅ | ✅ |
| **Fluent / typed chains** (`Assert::string()->…`, Pest `->not->`, `toBeGreaterThan`) | 🟡 *`TypedAssertChainRector` decomposes into separate `assert*` lines (incl. 1→N `hasKeys`, `between`); JSON path/structure, `isList`, `every`, custom matchers are left untouched + TODO* | ⛔ *reverse means **coalescing** independent statements into one chain — impractical* | ⛔ *negation is mappable, but blocked until a host class exists* |
| **Exception expectation (bare)** | 🟡 | 🟡 | ⛔ *(`->throws()` on `test()`)* |
| **Exception message/code (fluent)** `withMessage/withCode` ↔ `expectExceptionMessage/Code` | ✅ *`ExpectExceptionToPhpUnitRector` expands one chain into several statements (`withMessage`→`expectExceptionMessage`, `withCode`→`expectExceptionCode`, regex `withMessagePattern`→`expectExceptionMessageMatches`); substring `withMessageContaining` aborts the chain (no faithful PCRE target)* | ✅ *`ExpectExceptionToTestoRector` folds an uninterrupted run of sibling `expectExceptionMessage/Code` after `expectException` into the `->withMessage()/->withCode()` chain (StmtsAware); a non-foldable call ends the run* | ⛔ |
| **Exception message by regex** (`expectExceptionMessageMatches`) | ➖ | ⛔ *Testo's `withMessageContaining` is substring, not regex* | ➖ |
| **Skip** (`throw SkipTest` ↔ `markTestSkipped`) | ✅ | ✅ | ⛔ *(`->skip()`)* |
| **Incomplete** (`markTestIncomplete`) | ➖ | ⛔ *Testo has no Incomplete status* | ➖ |
| **Cancel** (`CancelTest`) | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Coverage attribute** (`#[Covers]` ↔ `#[CoversClass]`) | ✅ | ✅ | ➖ |
| **Lifecycle hooks** | ✅ *(attribute → attribute)* | ✅ *(`setUp/tearDown` → attributes)* | ⛔ *(`beforeEach` etc. — needs a class)* |
| **Class / method structure** (base class, `#[Test]`, discovery) | 🟡 *`TestClassToTestCaseRector` adds `extends TestCase` and converts `#[\Testo\Test]` (class-level → per-method `#[Test]` on public void/never methods; method-level → attribute rename); only classes that extend nothing; no method rename* | 🟡 *`ExtendsTestCaseToTestoRector` removes a **direct** `extends \PHPUnit\Framework\TestCase` and marks each test method (`#[Test]` / `@test` / `test`-prefix) with `#[\Testo\Test]`; intermediate/custom bases left untouched; no method rename* | ⛔ *`test()/it()` → method — core blocker* |
| **Data providers** (`#[DataProvider]`/`#[DataSet]` ↔ `->with`) | ✅ *`DataProviderToPhpUnitRector` renames `#[\Testo\Data\DataProvider]` → `#[DataProvider]` and `#[\Testo\Data\DataSet([…], 'label')]` → `#[TestWith([…], 'label')]` (both repeatable, args verbatim)* | ✅ *both `@dataProvider` annotation **and** `#[DataProvider]` attribute → `#[\Testo\Data\DataProvider]`; cross-class external form left as TODO* | ⛔ *(`->with` — needs a class)* |
| **Groups** (`#[Group]`) | ✅ *`GroupToPhpUnitRector` expands variadic → repeated `#[Group]`; `GroupInheritanceToPhpUnitRector` flattens both the class-level inheritance union (parents + traits) and the method-level prototype chain (a leaf method inherits the groups of the same-named parent-class method). Residual: traits are intentionally not consulted at method level — matches Testo, whose prototype walk skips them* | ✅ *`GroupToTestoRector` collapses `@group` annotations **and** repeated `#[Group]` into one variadic `#[\Testo\Filter\Group]`* | ⛔ *(`->group()` — needs a class)* |
| **ExpectNoAssertions** (`#[\Testo\Assert\ExpectNoAssertions]` ↔ `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`) | ✅ *`ExpectNoAssertionsToPhpUnitRector` (attribute rename; class-level → method fan-out left as TODO)* | ✅ *`DoesNotPerformAssertionsToTestoRector` (attribute rename)* | ➖ |
| **Mocks** (`createMock`/`getMockBuilder`/`prophesize`) | ➖ | ⛔ *Testo has no built-in mocking* | ➖ |
| **Memory-leak expectations** | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Retry / Repeat** (`#[Retry]`/`#[Repeat]`) | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **`uses()`** (Pest) | ➖ | ➖ | ⛔ *trait/base — needs a class* |
| **`arch()` tests** (Pest) | ➖ | ➖ | ⛔ *Testo has no arch-assertion subsystem* |

## Remaining work (🧩 — actually tractable)

No 🧩 items remain — the last one (structural class/method conversion) has shipped (see below).
Every outstanding direction is now either ✅, a 🟡 with a documented residual, or an intentional ⛔.

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
(coalescing separate asserts into one chain) stays out of scope. Also done: **fluent exception
message/code** in both directions — `ExpectExceptionToPhpUnitRector` expands the Testo chain into
several `$this->expect*` statements (`Node[]` return), and `ExpectExceptionToTestoRector` folds an
uninterrupted run of sibling `expectExceptionMessage/Code` calls back into the
`\Testo\Expect::exception(...)->withMessage()->withCode()` chain (operating on the StmtsAware
node's `->stmts`). **Residual:** Testo→PHPUnit cannot map the substring matcher
`withMessageContaining` (PHPUnit's only message matcher is the PCRE `expectExceptionMessageMatches`),
so a chain using it is left untouched rather than silently mistranslated — the same substring-vs-regex
mismatch that blocks the reverse `ExpectExceptionMessageMatchesRector`.

The remaining ⛔ rows are intentionally out of scope: they are either a missing target feature
(mocking, `arch()`, Incomplete, memory-leak, retry/repeat, PHPUnit `assertThat` constraints) or the
intractable Pest functional→class restructuring that every other Pest stub cascades from.
