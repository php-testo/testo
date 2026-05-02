<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Infection mutation testing bridge</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `bridge/infection/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Adapter that lets [Infection](https://github.com/infection/infection) drive Testo as the test framework for mutation testing. Wires Testo's `--filter` / `--teamcity` runtime to Infection's per-mutant invocation contract and consumes the PHPUnit-style coverage XML produced by `testo/codecov` for per-test attribution.

Auto-discovered by Infection through the `infection/extension-installer` plugin — no manual configuration beyond `"testFramework": "testo"` in `infection.json`.

## Install

```bash
composer require --dev testo/bridge-infection
```

[![PHP](https://img.shields.io/packagist/php-v/testo/bridge-infection.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/bridge-infection)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/bridge-infection.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/bridge-infection)
[![License](https://img.shields.io/packagist/l/testo/bridge-infection.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/bridge-infection.svg?style=flat-square)](https://packagist.org/packages/testo/bridge-infection/stats)
