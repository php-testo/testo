# Changelog

## [0.1.11](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.10...bridge-symfony-console-0.1.11) (2026-08-29)


### Bug Fixes

* `--log-html` without param reports to the default path ([#311](https://github.com/php-testo/testo/issues/311)) ([4275e2c](https://github.com/php-testo/testo/commit/4275e2c90fdc9c9dba98e996be8eb18ad95fa343))

## [0.1.10](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.9...bridge-symfony-console-0.1.10) (2026-08-25)


### Bug Fixes

* **output:** honour `--no-ansi` and `NO_COLOR` ([#299](https://github.com/php-testo/testo/issues/299)) ([9da803d](https://github.com/php-testo/testo/commit/9da803d40c3bca3f9612d72f23b7596854946dc1))

## [0.1.9](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.8...bridge-symfony-console-0.1.9) (2026-08-17)


### Features

* **codecov:** add --coverage-level=line|branch|path ([addbb76](https://github.com/php-testo/testo/commit/addbb76016ac5fc0b8d5473084887ab5182a967c))
* **report:** add a self-contained HTML report ([#291](https://github.com/php-testo/testo/issues/291)) ([d850a8c](https://github.com/php-testo/testo/commit/d850a8c71352312053ee4e27b4eaab0d5b5f2e88))


### Bug Fixes

* **codecov:** keep the configured level when CLI report flags activate coverage ([addbb76](https://github.com/php-testo/testo/commit/addbb76016ac5fc0b8d5473084887ab5182a967c))

## [0.1.8](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.7...bridge-symfony-console-0.1.8) (2026-06-29)


### Features

* **filter:** support test-type exclusion in `--type` ([#246](https://github.com/php-testo/testo/issues/246)) ([99b0c1e](https://github.com/php-testo/testo/commit/99b0c1e66c286be27bba870e3479e27eaa989626))


### Bug Fixes

* **filter:** declare test type on the #[Test] locator ([99b0c1e](https://github.com/php-testo/testo/commit/99b0c1e66c286be27bba870e3479e27eaa989626))
* **infection:** never run benches under mutation testing ([99b0c1e](https://github.com/php-testo/testo/commit/99b0c1e66c286be27bba870e3479e27eaa989626))


### Documentation

* **skills:** document --type exclusion ([99b0c1e](https://github.com/php-testo/testo/commit/99b0c1e66c286be27bba870e3479e27eaa989626))

## [0.1.7](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.6...bridge-symfony-console-0.1.7) (2026-06-19)


### Features

* **filter:** filtering with `#[Group]` attr and `--group` flag ([#227](https://github.com/php-testo/testo/issues/227)) ([624b9ab](https://github.com/php-testo/testo/commit/624b9ab877b5d4e0c24c2e594ac4da1890182df1))
* **filter:** support CLI options/arguments/env in `TestingSuite` ([624b9ab](https://github.com/php-testo/testo/commit/624b9ab877b5d4e0c24c2e594ac4da1890182df1))
* **output:** add JSON output plugin with `--json` and `--log-json` flags ([#228](https://github.com/php-testo/testo/issues/228)) ([abb91b3](https://github.com/php-testo/testo/commit/abb91b37a41ec1c10a28a8c0802a1c55411956a4))

## [0.1.6](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.5...bridge-symfony-console-0.1.6) (2026-06-07)


### Code Refactoring

* **messenger:** Move messenger into Core ([0ac2728](https://github.com/php-testo/testo/commit/0ac272898b9e24e240f1e301573c8102abbc2ce0))

## [0.1.5](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.4...bridge-symfony-console-0.1.5) (2026-06-06)


### Features

* **coverage:** add CLI flags for Clover, Cobertura, and XML coverage reports ([#204](https://github.com/php-testo/testo/issues/204)) ([f87365b](https://github.com/php-testo/testo/commit/f87365bacb1e1dab1f909258b698b28236f48bb1))

## [0.1.4](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.3...bridge-symfony-console-0.1.4) (2026-06-01)


### Features

* introduce Verbosity enum to manage console output levels ([f0359d7](https://github.com/php-testo/testo/commit/f0359d7f6063154042591437e539e160ef7be460))

## [0.1.3](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.2...bridge-symfony-console-0.1.3) (2026-05-20)


### Features

* Add `init` command ([#182](https://github.com/php-testo/testo/issues/182)) ([cd940d6](https://github.com/php-testo/testo/commit/cd940d6013d6e2942e8ae2e766d23f7d398313af))


### Bug Fixes

* **symfony-console:** Make init test compatible with Symfony 8 (no `add()`) ([42888e8](https://github.com/php-testo/testo/commit/42888e8be7cc518372ad58bbdcbe3c978e253cd9))
* **symfony-console:** Make init treat --path as the project root ([0f5de95](https://github.com/php-testo/testo/commit/0f5de956cdfd16505060742ee02c6af1f6927606))

## [0.1.2](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.1...bridge-symfony-console-0.1.2) (2026-05-05)


### Features

* Add JUnit output format with CLI flag `--log-junit` ([#122](https://github.com/php-testo/testo/issues/122)) ([c138d1d](https://github.com/php-testo/testo/commit/c138d1dc1481bca85f0851c7ca3e25427a3cecda))

## [0.1.1](https://github.com/php-testo/testo/compare/bridge-symfony-console-0.1.0...bridge-symfony-console-0.1.1) (2026-05-02)


### Code Refactoring

* **bridge-symfony-console:** Rename Symfony Console bridge ([0261f06](https://github.com/php-testo/testo/commit/0261f0641e7650cf92f5a3306bc5706bf8dcdf0b))
