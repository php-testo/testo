# Pest → Testo

Pest is a **functional DSL**: tests are file-level `test('…', fn)` / `it('…', fn)` calls with no
enclosing class, assertions are `expect($v)->toX()`, setup is `beforeEach()` / `uses()`, and data
comes from `->with([...])` / `dataset()`.

Testo is **attribute** based, and — crucially — it discovers plain **file-level functions** as tests
(a `#[\Testo\Test]` function, lifecycle hooks via `Testo\Lifecycle\*` on functions). That is the key
that unlocks this direction: a Pest `test()`/`it()` closure maps onto a free function, so there is no
need to fabricate a host class, namespace, PSR-4 location or `$this` binding. The old "Rector can't
synthesize the enclosing class, so the whole direction is intractable" blocker is gone — we don't
synthesize a class, we synthesize functions.

## What the set does (registered in `config/sets/pest-to-testo.php`)

- **`TestCallToFunctionRector`** — the core restructuring rule. Converts each file-level
  `test()` / `it()` call into a `#[\Testo\Test]` free function, and `beforeEach()` / `afterEach()` /
  `beforeAll()` / `afterAll()` into functions carrying the matching
  `Testo\Lifecycle\{BeforeTest,AfterTest,BeforeClass,AfterClass}` attribute. The description string
  is kept verbatim as the function docblock (Testo reads it as the test description) and is the
  source of a deterministic name — the declarator prefix (`test_` / `it_`) joined with the
  snake_cased description (`it('adds numbers')` → `it_adds_numbers`); in-file collisions get a `_2`,
  `_3`, … suffix. The whole fluent modifier chain is folded in the same pass (it only exists in the
  unconverted form, so a later rule could never see it):
    - `->group('a','b')`  → `#[\Testo\Filter\Group('a','b')]`
    - `->covers(X::class)` → `#[\Testo\Codecov\Covers(X::class)]`
    - `->throws(X::class[, 'msg'])` → prepended `\Testo\Expect::exception(X)[->withMessage('msg')]`, return type `never`
    - `->skip(['reason'])` → prepended `throw new \Testo\Core\Exception\SkipTest('reason')`
    - `->with([ <rows> ])` → one `#[\Testo\Data\DataSet([...])]` per row (inline array literal only)
- **`ExpectToAssertRector`** — runs after the structural rule and maps each
  `expect($value)->toX(...)` expectation inside the generated bodies to the matching actual-first
  `\Testo\Assert::*` call. See its own docblock for the mapped matchers.

These two rules absorb what used to be a fistful of separate stubs (the test/lifecycle/throws/skip/
group/dataset conversions): because every one of them operates on the same file-level call + fluent
chain that only exists *before* restructuring, they cannot be independent post-passes — they are part
of `TestCallToFunctionRector`'s single statement-level rewrite.

## Left visibly unconverted (so it is never silently mistranslated)

`TestCallToFunctionRector` leaves the **entire** statement untouched when it cannot restructure it
faithfully:

- a **non-string-literal description** (`test($name, fn)`) — no deterministic name can be derived;
- a **closure that captures outer variables** via `use (...)` — they have no home on a function;
- an **unknown / unsupported modifier** in the chain (`->only`, `->todo`, `->repeat`, `->depends`, a
  conditional `->skip(fn () => …)`, a named-dataset `->with('emails')`, …).

And two constructs remain documented stubs with **no faithful target**:

- **`UsesToTraitRector`** — `uses(BaseTestCase::class, SomeTrait::class)`. A converted function has no
  base class, no traits and no `$this`, so there is nothing to attach `extends`/`use` to. Shared
  state/lifecycle that Pest hung off `$this` must be re-expressed by hand. This is also why
  `TestCallToFunctionRector` bails on closures that capture `$this`-shared state.
- **`ArchTestRector`** — `arch()` tests. Testo has no architecture-assertion subsystem, so there is
  no API to translate into. Keep them in Pest or use a dedicated architecture-rule tool.

Not yet handled (candidates for a future pass, currently left as-is): `describe()` blocks (nested
naming + a per-block lifecycle scope), `dataset()` definitions and named `->with('name')` references
(need a `#[DataProvider]` source method), and `$this`-shared state across `beforeEach`/tests.

## What a user still does by hand

1. Re-express any `uses()` base/traits and `$this`-shared state (promote setup into
   `Testo\Lifecycle\*` hooks; port trait helpers to functions).
2. Finish chained / negated / unmapped `expect()` matchers that `ExpectToAssertRector` left alone.
3. Flatten `describe()` blocks and convert named datasets to `#[DataProvider]` providers.
4. Keep `arch()` tests in Pest or move them to a dedicated tool.
