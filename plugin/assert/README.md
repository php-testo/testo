<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Assert plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/assert/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Provides Testo's assertion machinery: a fluent assertion facade, expectation lifecycle, and exception-matching helpers. Tests can describe what they expect to happen and the plugin reports the comparison result through Testo's regular pipeline.

### Custom assertions

Domain-specific checks can use the public `Testo\Assert` facade without importing internal state:

```php
use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;

Assert::success($actual, 'satisfies the domain rule', 'optional context');
Assert::failure(new AssertionException(
    value: (string) $actual,
    assertion: 'satisfies the domain rule',
    context: '',
    reason: 'the rule was violated',
    details: '',
));
```

`success()` requires a non-empty description. `failure()` accepts only the public `AssertionException` contract (including `ComparisonFailure`), records it, and throws without returning; arbitrary throwables are unsupported. Empty descriptions are rejected before history changes. A caught failure remains in history and may leave the test passed, while matching `Expect::exception()` uses normal expectation handling. `Assert::fail()` retains its existing semantics.

## Install

```bash
composer require --dev testo/assert
```

[![PHP](https://img.shields.io/packagist/php-v/testo/assert.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/assert)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/assert.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/assert)
[![License](https://img.shields.io/packagist/l/testo/assert.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/assert.svg?style=flat-square)](https://packagist.org/packages/testo/assert/stats)
