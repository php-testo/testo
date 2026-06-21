# Changelog

## [0.1.11](https://github.com/php-testo/testo/compare/codecov-0.1.10...codecov-0.1.11) (2026-06-21)


### Bug Fixes

* **junit:** attribute inherited tests to the concrete class ([#231](https://github.com/php-testo/testo/issues/231)) ([fe81392](https://github.com/php-testo/testo/commit/fe8139215b98d018dfc3608af01867a8d6c79df5))

## [0.1.10](https://github.com/php-testo/testo/compare/codecov-0.1.9...codecov-0.1.10) (2026-06-09)


### Bug Fixes

* **codecov:** detect Xdebug coverage mode via xdebug_info('mode') ([703f9ca](https://github.com/php-testo/testo/commit/703f9ca54d29d15add1a32733a4964f81e4d3f89))

## [0.1.9](https://github.com/php-testo/testo/compare/codecov-0.1.8...codecov-0.1.9) (2026-06-07)


### Bug Fixes

* **codecov:** enforce --coverage driver requirement during configuration ([1e143d3](https://github.com/php-testo/testo/commit/1e143d309b1cd24b2fccf6c0db39c03c1b30e0a1))

## [0.1.8](https://github.com/php-testo/testo/compare/codecov-0.1.7...codecov-0.1.8) (2026-06-07)


### Code Refactoring

* **messenger:** Move messenger into Core ([0ac2728](https://github.com/php-testo/testo/commit/0ac272898b9e24e240f1e301573c8102abbc2ce0))

## [0.1.7](https://github.com/php-testo/testo/compare/codecov-0.1.6...codecov-0.1.7) (2026-06-06)


### Features

* **coverage:** add CLI flags for Clover, Cobertura, and XML coverage reports ([#204](https://github.com/php-testo/testo/issues/204)) ([f87365b](https://github.com/php-testo/testo/commit/f87365bacb1e1dab1f909258b698b28236f48bb1))

## [0.1.6](https://github.com/php-testo/testo/compare/codecov-0.1.5...codecov-0.1.6) (2026-05-29)


### Bug Fixes

* **codecov:** collect per-data-set coverage from MultipleResult child results ([b287f91](https://github.com/php-testo/testo/commit/b287f91d6e8e019a7e8566c6905c20dad0393c23))
* **data:** accumulate data sets across all provider attributes instead of keeping only the last ([b287f91](https://github.com/php-testo/testo/commit/b287f91d6e8e019a7e8566c6905c20dad0393c23))

## [0.1.5](https://github.com/php-testo/testo/compare/codecov-0.1.4...codecov-0.1.5) (2026-05-13)


### Bug Fixes

* **coverage:** Check for Xdebug coverage mode before initializing driver ([5254038](https://github.com/php-testo/testo/commit/52540384e80dde58697007c4ee42925f72b70b7b))

## [0.1.4](https://github.com/php-testo/testo/compare/codecov-0.1.3...codecov-0.1.4) (2026-05-07)


### Code Refactoring

* **codecov:** Enable covering Inline tests by default again ([4b9bc47](https://github.com/php-testo/testo/commit/4b9bc47491a6b448ae6042986a615f46429f035c))

## [0.1.3](https://github.com/php-testo/testo/compare/codecov-0.1.2...codecov-0.1.3) (2026-05-07)


### Code Refactoring

* **codecov:** Stop covering Inline tests by default because of [#159](https://github.com/php-testo/testo/issues/159) ([94ac94c](https://github.com/php-testo/testo/commit/94ac94c5ce146b08cc1f461e5bb907addb17d620))

## [0.1.2](https://github.com/php-testo/testo/compare/codecov-0.1.1...codecov-0.1.2) (2026-05-02)


### Bug Fixes

* **composer:** Switch testo/* requires from `^1.0@dev` to `0.1 - 1` ([403768f](https://github.com/php-testo/testo/commit/403768f9058cf09bca8c823f76e64530e31e1dc4))

## [0.1.1](https://github.com/php-testo/testo/compare/codecov-0.1.0...codecov-0.1.1) (2026-05-02)


### Code Refactoring

* **codecov:** Move Codecov plugin in a separated repository ([63983b1](https://github.com/php-testo/testo/commit/63983b13b811a50ddbe5dfbbca3630105d75700d))
* Normalize Test Suite naming ([9fd0cd3](https://github.com/php-testo/testo/commit/9fd0cd33f1ce6e6a49332be53d98bf6f78a15217))
