# Pest → Testo: mostly manual

**Read this before expecting the `pest-to-testo` set to migrate a Pest suite.**

Pest is a **functional DSL**: tests are file-level `test('…', fn)` / `it('…', fn)`
calls with no enclosing class, assertions are `expect($v)->toX()`, setup is
`beforeEach()` / `uses()`, and data comes from `->with([...])` / `dataset()`.

Testo is **class + attribute** based: tests are `#[\Testo\Test]` methods on a
class, assertions are `\Testo\Assert::*`, lifecycle is `Testo\Lifecycle\*`
attributes, data is `Testo\Data\{DataProvider,DataSet}`.

Rector transforms an existing AST **in place**. It does not synthesize new
program structure. The central problem for this whole direction is that there is
**no class to attach anything to**: Rector cannot reliably invent a class to wrap
file-level `test()` calls with correct naming, namespace/PSR-4 location, `$this`
binding, and lifecycle. So almost every Pest construct needs the test to already
be a class method — which is exactly the transform Rector can't do.

The honest split:

## What actually works (registered in `config/sets/pest-to-testo.php`)

- **`ExpectToAssertRector`** — converts a single
  `expect($value)->toX(...)` expectation into the matching actual-first
  `\Testo\Assert::*` static call. Mapped matchers:

  | Pest | Testo |
  | --- | --- |
  | `toBe($e)` | `\Testo\Assert::same($value, $e)` |
  | `toEqual($e)` | `\Testo\Assert::equals($value, $e)` |
  | `toBeTrue()` | `\Testo\Assert::true($value)` |
  | `toBeFalse()` | `\Testo\Assert::false($value)` |
  | `toBeNull()` | `\Testo\Assert::null($value)` |
  | `toBeInstanceOf($c)` | `\Testo\Assert::instanceOf($value, $c)` |
  | `toContain($x)` | `\Testo\Assert::contains($value, $x)` |
  | `toHaveCount($n)` | `\Testo\Assert::count($value, $n)` |

  This is local and reversible, so it is the one genuinely-tractable rule.
  **Left untouched on purpose** (so they stay visibly unconverted instead of
  silently mistranslated): chained expectations (`->toBe()->toBe()`), negated
  expectations (`->not->toBe(...)`), and unmapped/custom matchers
  (`toBeGreaterThan`, …).

## What is a stub (NOT registered — `refactor()` returns `null`)

Each stub is a documented placeholder describing intent and the manual work
required. None of them transform code.

- **`TestFunctionToMethodRector`** — `test()`/`it()` → `#[\Testo\Test]` method.
  *Blocker:* functional→class restructuring; Rector cannot fabricate the host
  class, derive a method name from a free-form description, rebind `$this`, or
  hoist sibling file-level statements. **This is the core blocker for the whole
  direction.**
- **`LifecycleHookToMethodRector`** — `beforeEach`/`afterEach`/`beforeAll`/
  `afterAll` → `Testo\Lifecycle\{BeforeTest,AfterTest,BeforeClass,AfterClass}`
  methods. *Blocker:* no host class to attach methods to; no defined `$this`.
- **`DatasetWithToDataProviderRector`** — `->with([...])` / `dataset()` →
  `Testo\Data\{DataSet,DataProvider}`. *Blocker:* needs a method with a real
  parameter list to bind rows onto; named datasets must move to a provider.
- **`ThrowsToExpectExceptionRector`** — `->throws(X)` chained on `test()` →
  `\Testo\Expect::exception(X)`. *Blocker:* the chain describes the whole test;
  there is no method body to insert the expectation into.
- **`SkipChainToSkipTestRector`** — `->skip()` →
  `throw new \Testo\Core\Exception\SkipTest(...)`. *Blocker:* needs a method body
  to throw from; conditional skips become `if`-guards.
- **`GroupChainToGroupAttributeRector`** — `->group(...)` →
  `#[\Testo\Filter\Group(...)]`. *Blocker:* no method/class declaration to
  annotate.
- **`UsesToTraitRector`** — `uses(...)` → class `extends`/`use`. *Blocker:* needs
  the host class, and the mapping is not one-to-one (Pest TestCase plumbing has
  no Testo analogue).
- **`ArchTestRector`** — Pest `arch()` tests. **Not convertible at all:** Testo
  has no architecture-assertion subsystem, so there is no target API. Documented
  only.

## What a user must do by hand

1. Create a Testo test class per Pest file (name, namespace, PSR-4 path).
2. Move each `test()` / `it()` closure body into a method; annotate the method
   (or the class) with `#[\Testo\Test]`.
3. Convert assertions with `ExpectToAssertRector` (run the set), then fix any
   chained / negated / unmapped expectations by hand.
4. Move `beforeEach`/`afterEach`/`beforeAll`/`afterAll` bodies into methods with
   the matching `Testo\Lifecycle\*` attribute; promote shared state to class
   properties.
5. Re-express datasets as `Testo\Data\DataSet` / `Testo\Data\DataProvider` with
   matching method parameters.
6. Replace `->throws()` with `\Testo\Expect::exception()`, `->skip()` with a
   `SkipTest` throw, `->group()` with `#[\Testo\Filter\Group]`.
7. Replace `uses()` with `extends` / `use` on the class (port helpers manually).
8. Keep `arch()` tests in Pest or use a dedicated architecture-rule tool.
