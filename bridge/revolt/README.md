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

### One test at a time

Only the test body is placed on the loop — right before the test, inner to the data provider, retries and every scoped-state guard — and the next test only enters once it has finished. Tests of a case never share a loop run, and the framework pipeline itself never parks on the loop.

That is what keeps the coroutines a test spawns attributable to it. Testo's scoped-state guards keep one active state and swap it only at fiber switches they drive themselves — and the loop's switches belong to the Revolt driver, which resumes parked fibers directly, past anything wrapping them. So the guards open their scopes outside the loop and never park during the dispatch: for as long as the test runs, the active state is simply this test's, and a coroutine started with `EventLoop::queue()` or `async()` reads it like the body does, however deep it nests. A second test on the same loop run would need that state swapped at switches nobody but the driver controls — hence one test at a time.

To interleave whole tests with each other, use `#[RunInFiber]` from `testo/fiber` — those run on plain fibers Testo itself drives.

```php
use Testo\Bridge\Revolt\RunInRevolt;

#[RunInRevolt]
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
