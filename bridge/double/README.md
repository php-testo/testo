<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Double bridge</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `bridge/double/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

[Double](https://github.com/jasonmccreary/double) is a modern PHP test-double library — one unified `Double` type covers mocks, stubs and spies. This bridge wires its verification into Testo: register `DoublePlugin` and `Double::verifyAll()` is called after every test, so `expects()` and `received()` assertions are always verified and the pending doubles are cleared between tests — no per-test `verify()` boilerplate.

```php
// testo.php
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Double\DoublePlugin;

return new ApplicationConfig(
    plugins: [new DoublePlugin()],
    suites:  [new SuiteConfig(name: 'Unit', location: ['tests/Unit'])],
);
```

```php
use JMac\Testing\Double;

$repository = Double::for(BookRepository::class);
$repository->expects('find')->with(123)->returns($book);

$service = new CatalogService($repository);
$service->lookup(123);
// The plugin verifies `find` was called as expected once the test returns.
```

## Install

```bash
composer require --dev testo/bridge-double
```

[![PHP](https://img.shields.io/packagist/php-v/testo/bridge-double.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/bridge-double)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/bridge-double.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/bridge-double)
[![License](https://img.shields.io/packagist/l/testo/bridge-double.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/bridge-double.svg?style=flat-square)](https://packagist.org/packages/testo/bridge-double/stats)
