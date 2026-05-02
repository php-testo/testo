# testo/repeat

Repeat policy plugin for the [Testo](https://github.com/php-testo/testo) testing framework.

Apply `#[Repeat]` to a test method/function/class to run the test multiple times in a row.
Combined with `#[Retry]`, the repeat cycle runs inside each retry attempt.

## Install

```bash
composer require --dev testo/repeat
```

## Usage

```php
use Testo\Repeat;
use Testo\Test;

#[Test]
final class FlakyDetectionTest
{
    #[Repeat(times: 5)]
    public function probablyDeterministic(): void
    {
        // executed 5 times; any single failure stops the loop and reports failure
    }
}
```

`#[Repeat]` can be placed on a method, a free function, or a whole class. When attached to a class, every test in it inherits the repeat policy unless overridden at the method level.

## Source

This package is split-published from the [`php-testo/testo`](https://github.com/php-testo/testo) monorepo (`plugin/repeat/`). Issues and pull requests should target the monorepo, not this read-only mirror.

## License

BSD-3-Clause. See [LICENSE](https://github.com/php-testo/testo/blob/1.x/LICENSE.md) in the monorepo.
