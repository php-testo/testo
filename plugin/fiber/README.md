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
- `Coroutine::spawn()` / `->await()` / `Coroutine::concurrently()` — add coroutines to the running test's schedule and wait for them; they interleave with the test body (and, under a class-level `#[RunInFiber]`, with the case's other tests) at every suspension point.

```php
#[RunInFiber]
public function pingPong(): void
{
    $server = Coroutine::spawn(fn(): string => $this->acceptAndEcho());
    $client = Coroutine::spawn(fn(): string => $this->connectAndSend('ping'));

    Assert::same($client->await(), 'pong');
    Assert::same($server->await(), 'ping');
}
```

The scope is structured: the test is not finished until every coroutine it spawned is. Coroutine failures always surface wrapped in a `CompositeException` — even a single one; if the test body fails, pending coroutines are cancelled with a `CancelledException` thrown into them, and an await cycle is broken with a `DeadlockException` at the guilty `await()`.

Switching is cooperative and happens only at suspension points — there is no event loop and no preemption. This is for fiber-based/cooperative code and race hunting, **not** for real async I/O: awaiting a timer, socket or `Future` needs the Revolt event loop — use the `testo/bridge-revolt` `#[RunInRevolt]` attribute for that.

Everything lives under the `Testo\Fiber\` namespace.

## Install

```bash
composer require --dev testo/fiber
```

[![PHP](https://img.shields.io/packagist/php-v/testo/fiber.svg?style=flat-square&logo=php)](https://packagist.org/packages/testo/fiber)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/testo/fiber.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/testo/fiber)
[![License](https://img.shields.io/packagist/l/testo/fiber.svg?style=flat-square)](https://github.com/php-testo/testo/blob/1.x/LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/testo/fiber.svg?style=flat-square)](https://packagist.org/packages/testo/fiber/stats)
