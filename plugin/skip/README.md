<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Skip attribute plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/skip/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Marks a test, a test class or a test function as skipped without deleting or hiding it. The test is not executed, but stays in every report as Skipped with its reason, so parked tests remain visible until someone returns to them.

The skip is declared ahead of time, next to the test; skipping at runtime from the test body is covered by the core `SkipTest` exception instead.

## Install

```bash
composer require --dev testo/skip
```

[![PHP](https://img.shields.io/packagist/php-v/testo/skip.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/skip)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/skip.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/skip)
[![License](https://img.shields.io/packagist/l/testo/skip.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/skip.svg?style=flat-square)](https://packagist.org/packages/testo/skip/stats)
