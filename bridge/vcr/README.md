<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">PHP-VCR bridge</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `bridge/vcr/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

[PHP-VCR](https://github.com/php-vcr/php-vcr) integration for Testo. Mark any test with `#[VCR('cassette')]`: its HTTP interactions are recorded to a cassette on the first run and replayed from it afterwards, so the test stays fast, deterministic, and offline. The attribute is self-wiring — no plugin registration required.

```php
use Testo\Attribute\Test;
use Testo\Bridge\VCR;
use Testo\Bridge\VCR\RecordMode;

#[Test]
#[VCR('github-user', mode: RecordMode::None)]
public function fetches_a_user(): void
{
    $json = \file_get_contents('https://api.github.com/users/roxblnfk');
    // asserts...
}
```

Register `VcrPlugin` only to point php-vcr at a non-default cassette directory:

```php
// testo.php
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\VCR\VcrPlugin;

return new ApplicationConfig(
    plugins: [new VcrPlugin(cassettePath: __DIR__ . '/tests/fixtures')],
    suites:  [new SuiteConfig(name: 'Feature', location: ['tests/Feature'])],
);
```

## Install

```bash
composer require --dev testo/bridge-vcr
```

[![PHP](https://img.shields.io/packagist/php-v/testo/bridge-vcr.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/bridge-vcr)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/bridge-vcr.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/bridge-vcr)
[![License](https://img.shields.io/packagist/l/testo/bridge-vcr.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/bridge-vcr.svg?style=flat-square)](https://packagist.org/packages/testo/bridge-vcr/stats)
