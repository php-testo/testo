<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Fiber / coroutine plugin</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `plugin/fiber/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Runs tests as plain PHP fibers driven by Testo's own cooperative scheduler, so a test (or the code it exercises) may suspend with `\Fiber::suspend()` and be resumed, and a case's tests may be interleaved to shake out order-dependent races.

- `#[RunInFiber]` — run a test (method) or a whole case (class) inside fibers, scheduled by `Schedule::Solo` (default), `RoundRobin` or `Random`.

Switching is cooperative and happens only at suspension points — there is no event loop and no preemption. This is for fiber-based/cooperative code and race hunting, **not** for real async I/O: awaiting a timer, socket or `Future` needs the Revolt event loop — use the `testo/bridge-revolt` `#[RunInRevolt]` attribute for that.

The attribute lives under the `Testo\Fiber\` namespace.

## Install

```bash
composer require --dev testo/fiber
```

[![PHP](https://img.shields.io/packagist/php-v/testo/fiber.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/fiber)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/fiber.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/fiber)
[![License](https://img.shields.io/packagist/l/testo/fiber.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/fiber.svg?style=flat-square)](https://packagist.org/packages/testo/fiber/stats)
