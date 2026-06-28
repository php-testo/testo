<p align="center">
    <a href="#get-started"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">The PHP Testing Framework You Control</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

[![Vibe Index](https://img.shields.io/static/v1?label=Vibe+Index&message=0.7&color=23b271&style=flat&logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2ZmZiI%2BPHBhdGggZD0iTTkgNCBROSAxMyAxOCAxMyBROSAxMyA5IDIyIFE5IDEzIDAgMTMgUTkgMTMgOSA0IFoiLz48cGF0aCBkPSJNMTkgMSBRMTkgNiAyNCA2IFExOSA2IDE5IDExIFExOSA2IDE0IDYgUTE5IDYgMTkgMSBaIi8%2BPHBhdGggZD0iTTIwIDE0IFEyMCAxOCAyNCAxOCBRMjAgMTggMjAgMjIgUTIwIDE4IDE2IDE4IFEyMCAxOCAyMCAxNCBaIi8%2BPC9zdmc%2B)](https://github.com/roxblnfk/action-vibe-index)
[![Psalm Level](https://shepherd.dev/github/php-testo/testo/level.svg)](https://shepherd.dev/github/php-testo/testo)
[![Type Coverage](https://shepherd.dev/github/php-testo/testo/coverage.svg)](https://shepherd.dev/github/php-testo/testo)
[![codecov](https://codecov.io/gh/php-testo/testo/branch/1.x/graph/badge.svg)](https://codecov.io/gh/php-testo/testo)
[![Mutation testing badge](https://img.shields.io/endpoint?url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fphp-testo%2Ftesto%2F1.x)](https://dashboard.stryker-mutator.io/reports/github.com/php-testo/testo/1.x)


</div>

<br />

Testo is an extensible testing framework built on a lightweight core with a middleware system.
It gives you full control over your testing environment while keeping the familiar PHP syntax you already know.


## Get Started

### Installation

```bash
composer require --dev testo/testo *
```

[![PHP](https://img.shields.io/packagist/php-v/testo/testo.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/testo)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/testo.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/testo)
[![License](https://img.shields.io/packagist/l/testo/testo.svg?style=flat-square)](LICENSE.md)
[![Total Destroys](https://img.shields.io/packagist/dt/testo/testo.svg?style=flat-square)](https://packagist.org/packages/testo/testo/stats)

### Configuration

The fastest way to set up Testo in your project is the built-in `init` command:

```bash
vendor/bin/testo init
```

It will:

- detect your `src/` directory (or prompt for it),
- create `tests/Unit/` if missing,
- generate a minimal `testo.php` next to your `composer.json`,
- register `composer test` and `composer test:<suite>` scripts.

For a sub-app layout, point it at the project root: `vendor/bin/testo init --path=app`.

#### Tuning `testo.php` manually

`testo.php` is plain PHP returning an `ApplicationConfig` — edit it freely to add suites, plugins, or coverage. A typical setup looks like:

```php
<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bench\BenchmarkPlugin;
use Testo\Inline\InlineTestPlugin;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Sources',
            location: ['src'],
            // Only Benchmarking and Inline Tests for Source files
            plugins: SuitePlugins::only(
                new InlineTestPlugin(),
                new BenchmarkPlugin(),
            ),
        ),
        new SuiteConfig(
            name: 'Unit',
            location: ['tests/Unit'],
        ),
    ],
);
```

If no `testo.php` is present at all, Testo falls back to running every test under the `tests/` folder.

### Running Tests

To run your tests, execute:

```bash
composer test
```

…or call the binary directly if you skipped `init` / didn't register the script:

```bash
vendor/bin/testo
```

You can also run a single suite by name (one script per detected suite is registered by `init`):

```bash
composer test:unit
composer test:integration
```

### Writing Your First Test

Create a test class in the configured test directory (e.g., `tests/CalculatorTest.php`):

```php
<?php

declare(strict_types=1);

namespace Tests;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Retry;
use Testo\Test;

#[Test]
final class CalculatorTest
{
    public function dividesNumbers(): void
    {
        $result = 10 / 2;

        Assert::same($result, 5.0);
        Assert::notSame($result, 5); // Types matter!
    }

    #[Retry(maxAttempts: 3)]
    public function flakyApiCall(): void
    {
        // Retries up to 3 times if test fails
        $response = $this->makeExternalApiCall();

        Assert::same($response->status, 200);
    }

    #[ExpectException(\RuntimeException::class)]
    public function throwsException(): void
    {
        throw new \RuntimeException('Expected error');
    }
}
```

What to note:
- Use the `#[Test]` attribute to mark test methods or classes
- Test classes don't need to extend any base class
- Use `Assert` class for assertions (`same`, `true`, `false`, `null`, `contains`, `instanceOf`, etc.)
- Testo provides multiple attributes to extend testing capabilities (retry policies, exception handling, and more)

## IDE Support

Testo comes with the [IDEA plugin `Testo`](https://plugins.jetbrains.com/plugin/28842-testo?noRedirect=true).

[![Version](https://img.shields.io/jetbrains/plugin/v/28842-testo?style=flat-square)](https://plugins.jetbrains.com/plugin/28842-testo/versions)
[![Rating](https://img.shields.io/jetbrains/plugin/r/rating/28842-testo?style=flat-square)](https://plugins.jetbrains.com/plugin/28842-testo/reviews)
[![Downloads](https://img.shields.io/jetbrains/plugin/d/28842-testo?style=flat-square)](https://plugins.jetbrains.com/plugin/28842-testo)
