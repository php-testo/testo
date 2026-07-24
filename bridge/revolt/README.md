<p align="center">
    <a href="https://github.com/php-testo/testo"><img alt="TESTO"
         src="https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true"
         style="width: 2in; display: block"
    /></a>
</p>

<p align="center">Revolt event-loop bridge</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://php-testo.github.io)
[![Support on Boosty](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsorship&logo=Boosty&logoColor=white&color=%23F15F2C)](https://boosty.to/roxblnfk)

</div>

<br />

> [!IMPORTANT]
> ## 🪞 This is a read-only mirror.
>
> Active development of the Testo project lives in [**php-testo/testo**](https://github.com/php-testo/testo) under `bridge/revolt/`. This repository is **automatically synchronized** from there on every release.
>
> File issues and pull requests in the [main monorepo](https://github.com/php-testo/testo/issues), not here.

## About

Runs a test on the process-global [Revolt](https://revolt.run) event loop, so the test body may `await` real async work (timers, streams, `Future::await()`, amphp libraries) and resume without blocking the process.

- `#[RunInRevolt]` — run a test on the Revolt event loop for real async I/O. Applied to a class, it drives every test in the case.

Suspension must go through a Revolt `Suspension` bound to a watcher (I/O, timer) — a bare `\Fiber::suspend()` has no resumer on the loop (that is the plain-fiber `#[RunInFiber]` from `testo/fiber`, a different concern).

### Strategy

`#[RunInRevolt]` takes a `Strategy` that chooses when the loop is entered:

- `Strategy::PerTest` (default) — each test's whole pipeline is placed on the loop individually, one test at a time, each run to completion before the next. No interleaving.
- `Strategy::PerCase` — launches the whole case's tests **concurrently** on one loop run via `CaseInfo`'s batch runner, interleaving at their await points.

Either way the whole per-test pipeline runs inside the loop fiber. Testo's scoped-state guards (assertion collector, messenger) hold their state per fiber, so concurrent tests stay isolated — each reads its own assertion/messenger state across every switch.

```php
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;

#[RunInRevolt(Strategy::PerTest)]
final class TimersTest { /* ... */ }
```

## Install

```bash
composer require --dev testo/bridge-revolt
```

[![PHP](https://img.shields.io/packagist/php-v/testo/bridge-revolt.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/bridge-revolt)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/bridge-revolt.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/bridge-revolt)
[![License](https://img.shields.io/packagist/l/testo/bridge-revolt.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/bridge-revolt.svg?style=flat-square)](https://packagist.org/packages/testo/bridge-revolt/stats)
