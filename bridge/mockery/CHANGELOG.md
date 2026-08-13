# Changelog

## [0.1.2](https://github.com/php-testo/testo/compare/bridge-mockery-0.1.1...bridge-mockery-0.1.2) (2026-08-06)


### Features

* **bridge-revolt:** add bridge for async tests using Revolt loop ([#269](https://github.com/php-testo/testo/issues/269)) ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))
* new internal/fiber package with the `FiberLocal` WeakMap primitive (draft) ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))


### Bug Fixes

* **plugin-fiber:** move the method-level fiber outside the per-test pipeline so the scoped-state guards run inside it ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))


### Code Refactoring

* **assert:** read the assertion state in `AssertJson` through `StaticState::current()` ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))

## [0.1.1](https://github.com/php-testo/testo/compare/bridge-mockery-0.1.0...bridge-mockery-0.1.1) (2026-07-03)


### Features

* **bridge-mockery:** add Mockery bridge (testo/bridge-mockery) ([#254](https://github.com/php-testo/testo/issues/254)) ([6f4596e](https://github.com/php-testo/testo/commit/6f4596ebe3120decfbb1e851e78ea06480917fde))

## Changelog
