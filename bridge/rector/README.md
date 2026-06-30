# testo/bridge-rector

[Rector](https://github.com/rectorphp/rector) rules to convert test suites between
**PEST**, **PHPUnit** and **Testo**.

Supported directions:

| Direction          | Status      | Purpose                                                              |
|--------------------|-------------|----------------------------------------------------------------------|
| Testo → PHPUnit    | in progress | Run tooling that expects PHPUnit (e.g. mutation testing) on a runner that shares no code with Testo's engine. |
| PEST → Testo       | planned     | Migrate an existing PEST suite to Testo.                             |
| PHPUnit → Testo    | planned     | Migrate an existing PHPUnit suite to Testo.                          |

## Why Testo → PHPUnit

Testo is self-hosted: the engine that discovers and runs tests is the same code
Infection mutates. A mutation in the run path (e.g. `Sorter`, `PipeOptions`) can
break discovery itself, producing spurious survivors/kills instead of a real
mutation signal.

Converting the unit-style self-tests to PHPUnit lets Infection's PHPUnit adapter
run them under a runner that is completely independent of the mutated engine, so a
mutation can only be caught (or missed) by an assertion — never by breaking the
harness.

### Argument order

Testo's comparison assertions take `(actual, expected)`, PHPUnit takes
`(expected, actual)`. The conversion swaps them — see
`AssertCallToPhpUnitRector`.

## Usage

```php
// rector.php
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/tests'])
    ->withSets([__DIR__ . '/vendor/testo/bridge-rector/config/sets/testo-to-phpunit.php']);
```
