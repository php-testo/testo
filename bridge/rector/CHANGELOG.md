# Changelog

## [0.2.3](https://github.com/php-testo/testo/compare/bridge-rector-0.2.2...bridge-rector-0.2.3) (2026-08-17)


### Features

* **report:** add a self-contained HTML report ([#291](https://github.com/php-testo/testo/issues/291)) ([d850a8c](https://github.com/php-testo/testo/commit/d850a8c71352312053ee4e27b4eaab0d5b5f2e88))

## [0.2.2](https://github.com/php-testo/testo/compare/bridge-rector-0.2.1...bridge-rector-0.2.2) (2026-08-10)


### Features

* **fiber:** coroutine scope — Coroutine::spawn()/await()/concurrently() ([#277](https://github.com/php-testo/testo/issues/277)) ([4d7b7ea](https://github.com/php-testo/testo/commit/4d7b7ea48d95af6fdf23f5fd13c6c81849ec7572))
* **pipeline:** make #[FallbackInterceptor] repeatable ([4d7b7ea](https://github.com/php-testo/testo/commit/4d7b7ea48d95af6fdf23f5fd13c6c81849ec7572))
* **teamcity:** report the exact status, assertion count, suite taxonomy and test count ([#278](https://github.com/php-testo/testo/issues/278)) ([d312824](https://github.com/php-testo/testo/commit/d312824ce0768361a707df63cc68d2e3afc9037a))

## [0.2.1](https://github.com/php-testo/testo/compare/bridge-rector-0.2.0...bridge-rector-0.2.1) (2026-08-06)


### Features

* **fiber:** new testo/fiber plugin — #[RunInFiber] cooperative-fiber tests ([#268](https://github.com/php-testo/testo/issues/268)) ([a109282](https://github.com/php-testo/testo/commit/a109282c0fbd50374826bdec60715db507e637c9))

## [0.2.0](https://github.com/php-testo/testo/compare/bridge-rector-0.1.2...bridge-rector-0.2.0) (2026-07-03)


### ⚠ BREAKING CHANGES

* **bridge-rector:** harden fixture path resolution and flatten config

### Features

* **bridge-rector:** add TestoRectorSetList for typed set references ([8b48967](https://github.com/php-testo/testo/commit/8b48967076cdc617992bb0c417adcddc0a2d84dc))


### Code Refactoring

* **bridge-rector:** harden fixture path resolution and flatten config ([d20176d](https://github.com/php-testo/testo/commit/d20176d3a3a86e0a05350d3aa4bc70b252839762))

## [0.1.2](https://github.com/php-testo/testo/compare/bridge-rector-0.1.1...bridge-rector-0.1.2) (2026-07-01)


### Features

* **bridge-rector:** merge adjacent same-head Assert chains (PHPUnit -&gt; Testo) ([c534e4e](https://github.com/php-testo/testo/commit/c534e4ee62327c685bc471af1b0ed775044c84cb))

## [0.1.1](https://github.com/php-testo/testo/compare/bridge-rector-0.1.0...bridge-rector-0.1.1) (2026-07-01)


### Features

* add Rector bridge for Testo/PHPUnit/Pest test conversion ([#248](https://github.com/php-testo/testo/issues/248)) ([a1953b7](https://github.com/php-testo/testo/commit/a1953b7484d507e3697fef78e14c3adb6475041b))
* mutation-test core/ with PHPUnit via a Rector-converted mirror ([#250](https://github.com/php-testo/testo/issues/250)) ([523eae7](https://github.com/php-testo/testo/commit/523eae740fb11046eed18f371b7189e469bb9188))


### Code Refactoring

* **assert:** disallow ExpectNoAssertions on a class ([3e156d7](https://github.com/php-testo/testo/commit/3e156d7025008ce33468f01ef2a1753abae18f6c))
* **bridge-rector:** Log fixtures into messenger in testing tool ([523eae7](https://github.com/php-testo/testo/commit/523eae740fb11046eed18f371b7189e469bb9188))
* **rector:** Log fixtures into messenger in testing tool ([8610abb](https://github.com/php-testo/testo/commit/8610abb03b9c54d7aa8b0c1fe0433481f9e412af))

## Changelog
