# testo/bridge-rector

[Rector](https://github.com/rectorphp/rector) rules to convert test suites between
**Pest**, **PHPUnit** and **Testo**.

## Directions & config sets

Each direction ships a Rector set. Reference it with the typed handle from
`Testo\Bridge\Rector\Set\TestoRectorSetList` rather than the raw file path:

| Direction       | Set constant                             | Status |
|-----------------|------------------------------------------|--------|
| Testo → PHPUnit | `TestoRectorSetList::TESTO_TO_PHPUNIT` | Assert calls, `Expect::exception` (bare), `throw SkipTest`, `#[Covers]`→`#[CoversClass]`, lifecycle attributes. |
| PHPUnit → Testo | `TestoRectorSetList::PHPUNIT_TO_TESTO` | Assert calls (arg-order restored), `expectException` (bare), `markTestSkipped`, `#[CoversClass]`→`#[Covers]`, lifecycle methods → attributes. |
| Pest → Testo    | `TestoRectorSetList::PEST_TO_TESTO`    | `expect()->toX()` → `Assert::*` only. The functional→class restructuring (`test()`/`it()` → methods) is **not** automatable — see `src/PestToTesto/TODO.md`. |

The set files live under `config/`; the constants are absolute paths to those files, so they
also work with `$rectorConfig->import(...)`.

`PHPUNIT_TO_TESTO` switches Rector to serial processing. A class that reaches `TestCase` through a project base class is recognised by the base's ancestry, and a parallel worker can read that base after another worker has already detached it, leaving the subclass's tests without `#[Test]` and silently undiscovered. Do not re-enable `withParallel()` for this set.

### Mock sets

Testo core ships no mocking, so test doubles convert through their own sets, one per target library.
Add one next to `PHPUNIT_TO_TESTO`, or run it on its own:

| Direction          | Set constant                             | Target |
|--------------------|------------------------------------------|--------|
| PHPUnit → Double   | `TestoRectorSetList::PHPUNIT_TO_DOUBLE`  | `createMock`/`createStub` and their `expects`/`method`/`will*`/`with` chains → `\JMac\Testing\Double` (`testo/bridge-double`). See `src/PhpunitToDouble/TODO.md`. |
| PHPUnit → Mockery  | `TestoRectorSetList::PHPUNIT_TO_MOCKERY` | The same chains → `\Mockery::mock()` + `shouldReceive()` (`testo/bridge-mockery`). See `src/PhpunitToMockery/TODO.md`. |
| Mockery → Double   | `TestoRectorSetList::MOCKERY_TO_DOUBLE`  | `mock`/`spy`, `shouldReceive`/`allows`/`expects`, `shouldHaveReceived`, `Mockery::close()` → Double, after Double's [migration table](https://testdoublephp.com/migrating-from-mockery). See `src/MockeryToDouble/TODO.md`. |

```php
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/tests'])
    ->withSets([TestoRectorSetList::PHPUNIT_TO_TESTO, TestoRectorSetList::PHPUNIT_TO_DOUBLE]);
```

Each mock rule rewrites a whole configuration statement or none of it. The rules do not track a
double across statements, though: when one statement on a double is left for manual work while the
double's factory and its other statements convert, the leftover one is what the finishing pass fixes.

### Polish set

`TestoRectorSetList::TESTO_POLISH` tidies tests that already run on Testo, typically as a second pass after a migration once the suite is green. It keeps the set of tests and their outcome unchanged:

- `: void` / `: never` return types (Rector's `AddVoidReturnTypeWhereNoReturnRector`, `ReturnNeverTypeRector`);
- `final` on a `*Test` class that carries `#[Test]` and has no subclass (`FinalizeTestClassRector`);
- `#[Test]` moved from the methods onto a `final` class when every public `void`/`never` method is a test or a lifecycle hook, and off a trait once every class using it has the class attribute (`ClassLevelTestAttributeRector`);
- a test that opens with a bare `Expect::exception(X::class)` and runs one statement → `#[ExpectException(X::class)]` (`ExpectExceptionToAttributeRector`);
- adjacent `Assert::<type>($var)` chains on the same variable merged into one pipe (`MergeAssertChainRector`).

Run it twice: a trait is cleaned up once its users carry the class attribute on disk. Scope it to the whole test tree and nothing else: the return-type rules apply to every method in the paths, and a class is left non-final only when a subclass shows up within them.

### Shift set

`TestoRectorSetList::TESTO_SHIFT` moves Testo code off deprecated Testo API onto its replacement, so a suite keeps working once the deprecated API is removed. Run it when upgrading Testo. Every entry is behaviour-preserving, since a deprecated method stays an alias of its replacement until removal:

- `ExpectedException::withMessagePattern()` → `withMessageMatchingRegex()` (Rector's `RenameMethodRector`, resolved by type, so any variable or chain holding the expectation is renamed).

Every Testo deprecation adds its migration to this set, with fixtures in `config/testo-shift/` (run by `tests/Unit/TestoShiftSetTest.php`).

Conversions that have no faithful counterpart in the target framework (constraints,
memory-leak / retry / repeat, Pest higher-order & `arch()` tests, etc.) are
**not silently dropped**: each is a documented stub rule plus an entry in the direction's
`TODO.md`.

### Argument order (important)

Testo's comparison assertions are `(actual, expected)`; PHPUnit's are `(expected, actual)`.
Both `AssertCall*` rules swap the first two arguments accordingly — getting this wrong would
silently invert every comparison, so it is covered by fixtures.

## Why Testo → PHPUnit

Testo is self-hosted: the engine that discovers and runs tests is the same code Infection
mutates. A mutation on the run path (e.g. `Sorter`, `PipeOptions`) can break discovery itself,
producing spurious survivors/kills instead of a real mutation signal. Converting the unit-style
self-tests to PHPUnit lets Infection's PHPUnit adapter run them on a runner that shares no code
with the mutated engine — a mutation can then only be caught (or missed) by an assertion, never
by breaking the harness.

## Usage

```php
// rector.php
use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\Set\TestoRectorSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/tests'])
    ->withSets([TestoRectorSetList::TESTO_TO_PHPUNIT]);
```

## Testing the rules — "inline tests for rules"

The rules are tested by Testo itself, with no PHPUnit dependency. A rule carries
`#[\Testo\Bridge\Rector\Testing\TestRectorFixtures('<dir>')]` pointing at co-located `*.php.inc`
fixtures (input + expected, separated by a `-----` line; no separator = "must stay unchanged").
Each declared path is relative to the rule's own directory (or absolute) and must resolve within
the working directory — an escaping path is rejected; declaring the attribute with no paths tests
nothing. The reusable harness lives in `src/Testing/` — attach `RectorTestingPlugin` to a suite whose
finder scans the rule sources, and each fixture is run through a freshly-booted Rector container
and reported as its own data set. Fixtures are `export-ignore`d; the harness ships so downstream
rule authors can reuse it (`testo/*` are `require-dev` + `suggest`).

### Coverage

Rector runs in the test process, so the fixtures count toward code coverage like any test. Each fixture's coverage is scoped to the rule it exercises (the harness attaches a `Testo\Codecov\CoverageScope`), which keeps the harness itself and the rest of the run out of it. A rule that delegates to helpers of its own widens the scope with `#[Covers]` on the rule class — list the rule too, since a declared `#[Covers]` replaces the default:

```php
#[TestRectorFixtures('MyRule')]
#[Covers(MyRule::class)]
#[Covers(MyHelper::class)]
final class MyRule extends AbstractRector { /* ... */ }
```

`#[CoversNothing]` on the rule class keeps its fixtures out of coverage.
