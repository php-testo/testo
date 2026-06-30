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
| **Exception message/code (fluent)** `withMessage/withCode` ↔ `expectExceptionMessage/Code` | 🧩 *needs `Node[]` return* | 🧩 *needs `Node[]` return* | ⛔ |
| **Exception message by regex** (`expectExceptionMessageMatches`) | ➖ | ⛔ *Testo's `withMessageContaining` is substring, not regex* | ➖ |
| **Skip** (`throw SkipTest` ↔ `markTestSkipped`) | ✅ | ✅ | ⛔ *(`->skip()`)* |
| **Incomplete** (`markTestIncomplete`) | ➖ | ⛔ *Testo has no Incomplete status* | ➖ |
| **Cancel** (`CancelTest`) | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Coverage attribute** (`#[Covers]` ↔ `#[CoversClass]`) | ✅ | ✅ | ➖ |
| **Lifecycle hooks** | ✅ *(attribute → attribute)* | ✅ *(`setUp/tearDown` → attributes)* | ⛔ *(`beforeEach` etc. — needs a class)* |
| **Class / method structure** (base class, `#[Test]`, discovery) | 🧩 *`TestClassToTestCase` (stub)* | 🧩 *`ExtendsTestCaseToTesto` (stub)* | ⛔ *`test()/it()` → method — core blocker* |
| **Data providers** (`#[DataProvider]`/`#[DataSet]` ↔ `->with`) | 🧩 *attribute↔attribute not done* | ✅ *both `@dataProvider` annotation **and** `#[DataProvider]` attribute → `#[\Testo\Data\DataProvider]`; cross-class external form left as TODO* | ⛔ *(`->with` — needs a class)* |
| **Groups** (`#[Group]`) | ✅ *`GroupToPhpUnitRector` expands variadic → repeated `#[Group]`; `GroupInheritanceToPhpUnitRector` flattens the class-level inheritance union (parents + traits) onto the leaf. Residual: method-level override-union not done* | ✅ *`GroupToTestoRector` collapses `@group` annotations **and** repeated `#[Group]` into one variadic `#[\Testo\Filter\Group]`* | ⛔ *(`->group()` — needs a class)* |
| **ExpectNoAssertions** (`#[\Testo\Assert\ExpectNoAssertions]` ↔ `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`) | ✅ *`ExpectNoAssertionsToPhpUnitRector` (attribute rename; class-level → method fan-out left as TODO)* | ✅ *`DoesNotPerformAssertionsToTestoRector` (attribute rename)* | ➖ |
| **Mocks** (`createMock`/`getMockBuilder`/`prophesize`) | ➖ | ⛔ *Testo has no built-in mocking* | ➖ |
| **Memory-leak expectations** | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **Retry / Repeat** (`#[Retry]`/`#[Repeat]`) | ⛔ *no PHPUnit equivalent* | ➖ | ➖ |
| **`uses()`** (Pest) | ➖ | ➖ | ⛔ *trait/base — needs a class* |
| **`arch()` tests** (Pest) | ➖ | ➖ | ⛔ *Testo has no arch-assertion subsystem* |

## Remaining work (🧩 — actually tractable)

In rough order of value / ease:

1. **Method-level group inheritance** (Testo → PHPUnit) — the *class-level* inheritance union is now
   flattened by `GroupInheritanceToPhpUnitRector` (parents + traits, via `ReflectionProvider`). The
   remaining gap is the *method-level* override-union: a leaf test method inheriting groups from the
   same method on a parent class/trait. It is the less common case and would need a per-method
   reflection walk reconciling overrides; deferred (tracked as a `@todo` in the rule).
2. **Data providers** — the **PHPUnit → Testo** side is done (annotation + attribute). The
   **Testo → PHPUnit** side (`#[\Testo\Data\DataProvider]`/`#[DataSet]` → `#[DataProvider]`/`#[TestWith]`)
   is the remaining mirror; close in shape to `DataProviderAttributeToTestoRector`.
3. **Exception fluent** `withMessage/withCode` ↔ `expectExceptionMessage/Code` (both directions) —
   requires switching those rules to return `Node[]` (one expression → several statements);
   `RectorInterface` permits it.
4. **Structural** `TestClassToTestCase` / `ExtendsTestCaseToTesto` (Testo ↔ PHPUnit) — hard but
   doable (Rector can add/remove `extends` and attributes); currently stubs.

Done since the first cut: **typed/fluent chain decomposition** (Testo → PHPUnit) now ships as
`TypedAssertChainRector` — `Assert::<type>($v)->m1()->m2()` → `assertIs<Type>($v)` plus one `assert*`
line per matcher (some expand 1→N), with unmapped matchers leaving the chain untouched. The reverse
(coalescing separate asserts into one chain) stays out of scope.

The remaining ⛔ rows are intentionally out of scope: they are either a missing target feature
(mocking, `arch()`, Incomplete, memory-leak, retry/repeat, PHPUnit `assertThat` constraints) or the
intractable Pest functional→class restructuring that every other Pest stub cascades from.
