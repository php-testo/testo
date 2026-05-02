<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Code coverage plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/codecov/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Collects line and branch coverage during test execution and emits reports in several CI-friendly formats: Clover, Cobertura, and PHPUnit-style coverage XML. Supports per-test attribution so downstream tools (Infection, code-quality dashboards) can map covered lines back to the test that exercised them.

## Install

```bash
composer require --dev testo/codecov
```

[![PHP](https://img.shields.io/packagist/php-v/testo/codecov.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/codecov)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/codecov.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/codecov)
[![License](https://img.shields.io/packagist/l/testo/codecov.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/codecov.svg?style=flat-square)](https://packagist.org/packages/testo/codecov/stats)
