# Changelog

## [0.1.10](https://github.com/php-testo/testo/compare/bench-0.1.9...bench-0.1.10) (2026-08-27)


### Bug Fixes

* **bench:** partition the variance bands cleanly at frstdev == 10.0 ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))
* **rector:** resolve rule instances via findByContract, require rector/rector ^2.6.4 (Rector 2.6.4 dropped RectorConfig::tagged(); rectorphp/rector[#9870](https://github.com/php-testo/testo/issues/9870)) ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))
* **rector:** surface a rule setup failure as an errored data set ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))


### Code Refactoring

* **codecov:** null-coalescing assignment in CoberturaReport ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))

## [0.1.9](https://github.com/php-testo/testo/compare/bench-0.1.8...bench-0.1.9) (2026-08-25)


### Features

* **bench:** fail a benchmark when `current` is not the fastest ([0f032fd](https://github.com/php-testo/testo/commit/0f032fd1c8ad5c57714a0ea6d878b332776cf7ee)), closes [#303](https://github.com/php-testo/testo/issues/303)


### Bug Fixes

* **bench:** group recommendations by advice instead of repeating it ([af627db](https://github.com/php-testo/testo/commit/af627db9c1936c5930256bef3fd390d1ab97217f)), closes [#303](https://github.com/php-testo/testo/issues/303)
* **bench:** guard filtered RStDev against a zero mean ([f82fdef](https://github.com/php-testo/testo/commit/f82fdefcb57f014df126d4ba9f70f5e0570f0c52)), closes [#303](https://github.com/php-testo/testo/issues/303)
* **bench:** guard the mean percentage against a zero baseline mean ([82d767f](https://github.com/php-testo/testo/commit/82d767fbc2451f62ec0758dd8e46ecc580150c68)), closes [#303](https://github.com/php-testo/testo/issues/303)
* **bench:** keep the results table in declaration order ([a07e93f](https://github.com/php-testo/testo/commit/a07e93ff3d630e7bef66c3a9400439a2ab2c9ad1)), closes [#303](https://github.com/php-testo/testo/issues/303)
* **bench:** make `[class-string, method]` reach a non-public method ([84e5013](https://github.com/php-testo/testo/commit/84e5013e10d2645bf839b80daef413c224b7759b)), closes [#303](https://github.com/php-testo/testo/issues/303)
* **bench:** measure peak memory per iteration instead of an end-to-end delta ([#300](https://github.com/php-testo/testo/issues/300)) ([67cac86](https://github.com/php-testo/testo/commit/67cac866713f01d1da09a6a516187a472e814d0c))
* **bench:** render the results table fastest-first with aligned numeric columns ([9e6b560](https://github.com/php-testo/testo/commit/9e6b5605aaf4fb27306917bcd23793fde9641da0)), closes [#303](https://github.com/php-testo/testo/issues/303)
* **bench:** stop rejecting every off-median sample when MAD is zero ([05cb384](https://github.com/php-testo/testo/commit/05cb384091bc3b7a2a939b7327801c4b50840c1d)), closes [#303](https://github.com/php-testo/testo/issues/303)


### Documentation

* **bench:** distinguish the 2% reader bar from the diagnostic threshold ([087faf5](https://github.com/php-testo/testo/commit/087faf587d89ec66ab6920d0a721bff3b3e9f978)), closes [#303](https://github.com/php-testo/testo/issues/303)
* **bench:** document the warmup default and the per-call harness overhead ([8f9c356](https://github.com/php-testo/testo/commit/8f9c356a2de19ca5c10be7097b671ee606fe3f40)), closes [#303](https://github.com/php-testo/testo/issues/303)

## [0.1.8](https://github.com/php-testo/testo/compare/bench-0.1.7...bench-0.1.8) (2026-08-10)


### Features

* **teamcity:** report the exact status, assertion count, suite taxonomy and test count ([#278](https://github.com/php-testo/testo/issues/278)) ([d312824](https://github.com/php-testo/testo/commit/d312824ce0768361a707df63cc68d2e3afc9037a))

## [0.1.7](https://github.com/php-testo/testo/compare/bench-0.1.6...bench-0.1.7) (2026-08-06)


### Features

* **core:** Add `Identity` to test context ([#271](https://github.com/php-testo/testo/issues/271)) ([f17f4ed](https://github.com/php-testo/testo/commit/f17f4ed347986f4ea857f5def3c7425bb02683db))

## [0.1.6](https://github.com/php-testo/testo/compare/bench-0.1.5...bench-0.1.6) (2026-07-01)


### Features

* add Rector bridge for Testo/PHPUnit/Pest test conversion ([#248](https://github.com/php-testo/testo/issues/248)) ([a1953b7](https://github.com/php-testo/testo/commit/a1953b7484d507e3697fef78e14c3adb6475041b))

## [0.1.5](https://github.com/php-testo/testo/compare/bench-0.1.4...bench-0.1.5) (2026-06-07)


### Code Refactoring

* **messenger:** Move messenger into Core ([0ac2728](https://github.com/php-testo/testo/commit/0ac272898b9e24e240f1e301573c8102abbc2ce0))

## [0.1.4](https://github.com/php-testo/testo/compare/bench-0.1.3...bench-0.1.4) (2026-06-06)


### Features

* **core:** aggregate run statistics via Summary DTO and show assertions ([cd20c42](https://github.com/php-testo/testo/commit/cd20c42e034376721c820dc34d628ba5c1598f63))

## [0.1.3](https://github.com/php-testo/testo/compare/bench-0.1.2...bench-0.1.3) (2026-06-01)


### Code Refactoring

* **bench:** Send benchmark results into messenger ([e7fa77a](https://github.com/php-testo/testo/commit/e7fa77a8d9a3ae49ab27ddf05a9cbd0d082b389c))

## [0.1.2](https://github.com/php-testo/testo/compare/bench-0.1.1...bench-0.1.2) (2026-05-02)


### Bug Fixes

* **composer:** Switch testo/* requires from `^1.0@dev` to `0.1 - 1` ([403768f](https://github.com/php-testo/testo/commit/403768f9058cf09bca8c823f76e64530e31e1dc4))

## [0.1.1](https://github.com/php-testo/testo/compare/bench-0.1.0...bench-0.1.1) (2026-05-02)


### Code Refactoring

* **bench:** Move Bench plugin in a separated repository ([fd0fd73](https://github.com/php-testo/testo/commit/fd0fd735c164de256289b902e1227d4bcf653036))
* Normalize Test Suite naming ([9fd0cd3](https://github.com/php-testo/testo/commit/9fd0cd33f1ce6e6a49332be53d98bf6f78a15217))
