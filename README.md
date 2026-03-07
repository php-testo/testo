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

By default, if no configuration file is provided, Testo will run tests from the `tests` folder.

To customize the configuration, create a `testo.php` file in the root of your project:

```php
<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: new FinderConfig(
                include: ['tests/Unit'],
            ),
        ),
    ],
);
```

### Running Tests

To run your tests, execute:

```bash
vendor/bin/testo
```

### Writing Your First Test

Create a test class in the configured test directory (e.g., `tests/CalculatorTest.php`):

```php
<?php

declare(strict_types=1);

namespace Tests;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Attribute\Test;
use Testo\Retry\RetryPolicy;

final class CalculatorTest
{
    #[Test]
    public function dividesNumbers(): void
    {
        $result = 10 / 2;

        Assert::same(5.0, $result);
        Assert::notSame(5, $result); // Types matter!
    }

    #[Test]
    #[RetryPolicy(maxAttempts: 3)]
    public function flakyApiCall(): void
    {
        // Retries up to 3 times if test fails
        $response = $this->makeExternalApiCall();

        Assert::same(200, $response->status);
    }

    #[Test]
    #[ExpectException(\RuntimeException::class)]
    public function throwsException(): void
    {
        throw new \RuntimeException('Expected error');
    }
}
```

What to note:
- Use the `#[Test]` attribute to mark test methods
- Test classes don't need to extend any base class
- Use `Assert` class for assertions (`same`, `true`, `false`, `null`, `contains`, `instanceOf`, etc.)
- Testo provides multiple attributes to extend testing capabilities (retry policies, exception handling, and more)

## IDE Support

Testo comes with the [IDEA plugin `Testo`](https://plugins.jetbrains.com/plugin/28842-testo?noRedirect=true).

[![Version](https://img.shields.io/jetbrains/plugin/v/28842-testo?style=flat-square)](https://plugins.jetbrains.com/plugin/28842-testo/versions)
[![Rating](https://img.shields.io/jetbrains/plugin/r/rating/28842-testo?style=flat-square)](https://plugins.jetbrains.com/plugin/28842-testo/reviews)
[![Downloads](https://img.shields.io/jetbrains/plugin/d/28842-testo?style=flat-square)](https://plugins.jetbrains.com/plugin/28842-testo)
