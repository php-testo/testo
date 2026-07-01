# Changelog

## [0.1.10](https://github.com/php-testo/testo/compare/assert-0.1.9...assert-0.1.10) (2026-07-01)


### Code Refactoring

* **assert:** disallow ExpectNoAssertions on a class ([3e156d7](https://github.com/php-testo/testo/commit/3e156d7025008ce33468f01ef2a1753abae18f6c))

## [0.1.9](https://github.com/php-testo/testo/compare/assert-0.1.8...assert-0.1.9) (2026-06-26)


### Features

* **assert:** Expose `#[ExpectNoAssertions]` attribute ([#116](https://github.com/php-testo/testo/issues/116)) ([d72d2ea](https://github.com/php-testo/testo/commit/d72d2ea5505199b8bb0d7e69c7a38ef97ed7dd13))

## [0.1.8](https://github.com/php-testo/testo/compare/assert-0.1.7...assert-0.1.8) (2026-06-19)


### Features

* **assert:** add `notContains` assertion to iterable type ([c320fbf](https://github.com/php-testo/testo/commit/c320fbf1501911dcc0c079a68039531a730eee99))

## [0.1.7](https://github.com/php-testo/testo/compare/assert-0.1.6...assert-0.1.7) (2026-06-07)


### Code Refactoring

* **messenger:** Move messenger into Core ([0ac2728](https://github.com/php-testo/testo/commit/0ac272898b9e24e240f1e301573c8102abbc2ce0))

## [0.1.6](https://github.com/php-testo/testo/compare/assert-0.1.5...assert-0.1.6) (2026-06-06)


### Features

* **core:** aggregate run statistics via Summary DTO and show assertions ([cd20c42](https://github.com/php-testo/testo/commit/cd20c42e034376721c820dc34d628ba5c1598f63))


### Code Refactoring

* **testing:** update TestRunner namespace from Traits to Helper ([14ebce3](https://github.com/php-testo/testo/commit/14ebce3a0191f82e8dbf5ae145dd3c435ef64fd0))

## [0.1.5](https://github.com/php-testo/testo/compare/assert-0.1.4...assert-0.1.5) (2026-06-02)


### Code Refactoring

* **assert:** Migrate Assert plugin to channels ([9a13ff1](https://github.com/php-testo/testo/commit/9a13ff17efea95b4ab8289dea8913b3ec4f96a30))

## [0.1.4](https://github.com/php-testo/testo/compare/assert-0.1.3...assert-0.1.4) (2026-05-20)


### Features

* **assert:** Expose `Assert::notNull()` ([#189](https://github.com/php-testo/testo/issues/189)) ([6523c6a](https://github.com/php-testo/testo/commit/6523c6ad8b9c9bf3c40d721b4347488f14809bc3))

## [0.1.3](https://github.com/php-testo/testo/compare/assert-0.1.2...assert-0.1.3) (2026-05-15)


### Bug Fixes

* **assert:** Add missing psalm/phpstan assert annotations ([#179](https://github.com/php-testo/testo/issues/179)) ([0af9eb0](https://github.com/php-testo/testo/commit/0af9eb082c5a54f79657ff47cfca47ffeef6f0c3))

## [0.1.2](https://github.com/php-testo/testo/compare/assert-0.1.1...assert-0.1.2) (2026-05-09)


### Features

* **assert:** Add `ComparisonFailure` ([46c7045](https://github.com/php-testo/testo/commit/46c704591af17a66f25c4b4e674b6d9c51b5db9a))
* **assert:** Add DIFF into output ([bf41558](https://github.com/php-testo/testo/commit/bf415588296700bbcb56ce08d59b9f1fcd0f9ea6))


### Code Refactoring

* **assert:** Remove `AssertException` and use `AssertionException` instead ([b4166f6](https://github.com/php-testo/testo/commit/b4166f65b1832156dbad289525750b2729f11354))

## [0.1.1](https://github.com/php-testo/testo/compare/assert-0.1.0...assert-0.1.1) (2026-05-02)


### Code Refactoring

* **assert:** Move Assert plugin in a separated repository ([12d2796](https://github.com/php-testo/testo/commit/12d27966971e2451286bdd224d0f19d8e6d27b06))
* Normalize Test Suite naming ([9fd0cd3](https://github.com/php-testo/testo/commit/9fd0cd33f1ce6e6a49332be53d98bf6f78a15217))
