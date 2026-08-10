# Changelog

## [0.1.2](https://github.com/php-testo/testo/compare/fiber-0.1.1...fiber-0.1.2) (2026-08-10)


### Features

* **fiber:** coroutine scope — Coroutine::spawn()/await()/concurrently() ([#277](https://github.com/php-testo/testo/issues/277)) ([4d7b7ea](https://github.com/php-testo/testo/commit/4d7b7ea48d95af6fdf23f5fd13c6c81849ec7572))
* **pipeline:** make #[FallbackInterceptor] repeatable ([4d7b7ea](https://github.com/php-testo/testo/commit/4d7b7ea48d95af6fdf23f5fd13c6c81849ec7572))

## [0.1.1](https://github.com/php-testo/testo/compare/fiber-0.1.0...fiber-0.1.1) (2026-08-06)


### Features

* **bridge-revolt:** add bridge for async tests using Revolt loop ([#269](https://github.com/php-testo/testo/issues/269)) ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))
* **fiber:** new testo/fiber plugin — #[RunInFiber] cooperative-fiber tests ([#268](https://github.com/php-testo/testo/issues/268)) ([a109282](https://github.com/php-testo/testo/commit/a109282c0fbd50374826bdec60715db507e637c9))
* new internal/fiber package with the `FiberLocal` WeakMap primitive (draft) ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))


### Bug Fixes

* **plugin-fiber:** move the method-level fiber outside the per-test pipeline so the scoped-state guards run inside it ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))


### Code Refactoring

* **assert:** read the assertion state in `AssertJson` through `StaticState::current()` ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))
