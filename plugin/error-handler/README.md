<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Error handler plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/error-handler/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Puts an error handler around every test and records the PHP warnings, notices and deprecations it raises on the test result, so they no longer scroll by while the test passes. Unhandled errors are printed to the stderr channel as PHP would print them, and with `failOnError` any captured error fails the test.

A handler installed before the test, such as an application bootstrap, still runs first. A test that installs its own handler and leaves it in place is reported as Risky unless it declares the change with `#[ExpectErrorHandlerChange]`.

## Install

```bash
composer require --dev testo/error-handler
```

[![PHP](https://img.shields.io/packagist/php-v/testo/error-handler.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/error-handler)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/error-handler.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/error-handler)
[![License](https://img.shields.io/packagist/l/testo/error-handler.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/error-handler.svg?style=flat-square)](https://packagist.org/packages/testo/error-handler/stats)
