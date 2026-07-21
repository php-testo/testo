<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Async / coroutine plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/async/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Runs tests as fibers/coroutines on the process-global [Revolt](https://revolt.run) event loop, so a test may suspend on plain fibers *and* await real async work (timers, streams, amphp libraries) without blocking the process.

- `#[RunInCoroutine]` — run a single test as its own isolated coroutine on the Revolt event loop (for real async I/O).
- `#[Concurrent]` — schedule a case's tests: sequential (default) or cooperative `RoundRobin` / `Random` interleaving on plain fibers, to shake out order-dependent races.

Both attributes live under the `Testo\Async\` namespace.

## Install

```bash
composer require --dev testo/async
```

[![PHP](https://img.shields.io/packagist/php-v/testo/async.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/async)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/async.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/async)
[![License](https://img.shields.io/packagist/l/testo/async.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/async.svg?style=flat-square)](https://packagist.org/packages/testo/async/stats)
