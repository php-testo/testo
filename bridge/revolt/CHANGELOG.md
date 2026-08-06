# Changelog

## [0.1.1](https://github.com/php-testo/testo/compare/bridge-revolt-0.1.0...bridge-revolt-0.1.1) (2026-08-06)


### Features

* **bridge-revolt:** add bridge for async tests using Revolt loop ([#269](https://github.com/php-testo/testo/issues/269)) ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))
* new internal/fiber package with the `FiberLocal` WeakMap primitive (draft) ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))


### Bug Fixes

* **codecov:** make per-test coverage fiber-aware ([#276](https://github.com/php-testo/testo/issues/276)) ([8d6f46d](https://github.com/php-testo/testo/commit/8d6f46d640c9befbda619831e35f14557c7eade8))
* **plugin-fiber:** move the method-level fiber outside the per-test pipeline so the scoped-state guards run inside it ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))


### Code Refactoring

* **assert:** read the assertion state in `AssertJson` through `StaticState::current()` ([b109c63](https://github.com/php-testo/testo/commit/b109c6322b4f0cc99bf375cd77db7434e47890fc))
