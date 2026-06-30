# testo/bridge-rector

[Rector](https://github.com/rectorphp/rector) rules to convert test suites between
**Pest**, **PHPUnit** and **Testo**.

## Directions & config sets

Each direction ships a Rector set under `config/sets/`:

| Direction       | Set                              | Status |
|-----------------|----------------------------------|--------|
| Testo → PHPUnit | `config/sets/testo-to-phpunit.php` | Assert calls, `Expect::exception` (bare), `throw SkipTest`, `#[Covers]`→`#[CoversClass]`, lifecycle attributes. |
| PHPUnit → Testo | `config/sets/phpunit-to-testo.php` | Assert calls (arg-order restored), `expectException` (bare), `markTestSkipped`, `#[CoversClass]`→`#[Covers]`, lifecycle methods → attributes. |
| Pest → Testo    | `config/sets/pest-to-testo.php`    | `expect()->toX()` → `Assert::*` only. The functional→class restructuring (`test()`/`it()` → methods) is **not** automatable — see `src/PestToTesto/TODO.md`. |

Conversions that have no faithful counterpart in the target framework (PHPUnit mocks,
constraints, memory-leak / retry / repeat, Pest higher-order & `arch()` tests, etc.) are
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

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/tests'])
    ->withSets([__DIR__ . '/vendor/testo/bridge-rector/config/sets/testo-to-phpunit.php']);
```

## Testing the rules — "inline tests for rules"

The rules are tested by Testo itself, with no PHPUnit dependency. A rule carries
`#[\Testo\Bridge\Rector\Testing\TestRectorFixtures('<dir>')]` pointing at co-located `*.php.inc`
fixtures (input + expected, separated by a `-----` line; no separator = "must stay unchanged").
The reusable harness lives in `src/Testing/` — attach `RectorTestingPlugin` to a suite whose
finder scans the rule sources, and each fixture is run through a freshly-booted Rector container
and reported as its own data set. Fixtures are `export-ignore`d; the harness ships so downstream
rule authors can reuse it (`testo/*` are `require-dev` + `suggest`).
