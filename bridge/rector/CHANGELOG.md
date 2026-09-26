# Changelog

## [0.3.2](https://github.com/php-testo/testo/compare/bridge-rector-0.3.1...bridge-rector-0.3.2) (2026-09-26)


### Features

* **assert:** add startsWith() and endsWith() to string assertions ([03f3ec4](https://github.com/php-testo/testo/commit/03f3ec46ea0b5ec24e5b68a509b0ee2f95fd2189))
* **assert:** add static and return type checks to callable assertions ([c9b0624](https://github.com/php-testo/testo/commit/c9b0624dd127b55c113d9f0f62314da17cb5f3b7))
* **bridge-rector:** add ClassLevelTestAttributeRector ([9c4f567](https://github.com/php-testo/testo/commit/9c4f5672fcdb9c9d3cf90dcf2816ad149a04aeb7))
* **bridge-rector:** add ExpectExceptionToAttributeRector ([6b7a0d7](https://github.com/php-testo/testo/commit/6b7a0d7b81d6457e686169a58007dcbf54471d4e))
* **bridge-rector:** add FinalizeTestClassRector ([1ef621c](https://github.com/php-testo/testo/commit/1ef621c0a10d4f06ecd3e897001a27b9ad550321))
* **bridge-rector:** add the testo-polish set ([13c8fac](https://github.com/php-testo/testo/commit/13c8facfee549161c5ce0bb4f48eb2344b0993b7))
* **bridge-rector:** add the testo-shift set for deprecated Testo API ([c7712e8](https://github.com/php-testo/testo/commit/c7712e80c2487013ae441ed2f778922ae358739f))
* **bridge-rector:** convert assertContainsOnlyInstancesOf to allInstanceOf() ([2384981](https://github.com/php-testo/testo/commit/238498141301105f9fb4495b11b21f201ea2bcab))
* **bridge-rector:** convert assertJson to Assert::json ([8a35827](https://github.com/php-testo/testo/commit/8a358274ccae0364a722d4d531532d647bf0c7f0))
* **bridge-rector:** convert assertNotTrue and assertNotFalse to Assert::notSame ([42ba8e6](https://github.com/php-testo/testo/commit/42ba8e67efc60160ef883245290b048d3f6298f7))
* **bridge-rector:** convert bool and callable type assertions ([9942755](https://github.com/php-testo/testo/commit/99427552c3970c00e96622553238c26ce7abb112))
* **bridge-rector:** convert contains-only and same-size assertions ([210ef07](https://github.com/php-testo/testo/commit/210ef0712ecd5157fa5f48a51057cdf9e5cf9392))
* **bridge-rector:** convert path permission, math, resource and negated type checks ([48799df](https://github.com/php-testo/testo/commit/48799df11d335ce9bc070d704c28f5732ffdc427))
* **bridge-rector:** convert PHPUnit regular-expression assertions to Testo ([9804057](https://github.com/php-testo/testo/commit/980405731952e63997d465f8a1e8acf1a89d6e54))
* **bridge-rector:** convert substring, not-contains, property and list assertions ([75d079a](https://github.com/php-testo/testo/commit/75d079a5c147a98a941fc743ce0849c25ca0fb6c))
* **bridge-rector:** convert Testo pattern matchers to PHPUnit regular-expression assertions ([1d87843](https://github.com/php-testo/testo/commit/1d8784380ba515864aa502e51c6e5bedd7eb1dcd))
* **bridge-rector:** convert the string assertions that ignore case, line endings or whitespace ([76a99c5](https://github.com/php-testo/testo/commit/76a99c5f9438bdf536d4d490a6dcda69c55f987a))
* **bridge-rector:** convert type, file and string-prefix assertions ([ff80cac](https://github.com/php-testo/testo/commit/ff80cac1370fb0a23e530fc2d641fa7b24106e12))
* **bridge-rector:** decompose Assert::iterable() chains and allInstanceOf() into PHPUnit ([3d92656](https://github.com/php-testo/testo/commit/3d92656b73f7be80f66412da0ddd41d7eae82d97))
* **bridge-rector:** drop redundant #[Test] from traits in testo-polish ([f201cac](https://github.com/php-testo/testo/commit/f201cac1a90a9f0a847478137e81e04863bf9265))


### Bug Fixes

* **bridge-rector:** convert doc tags shadowed by an imported PHPUnit attribute ([c77404c](https://github.com/php-testo/testo/commit/c77404c8c7a06fa75748d77b357af3b030b12757))
* **bridge-rector:** convert the PHPUnit\Framework\assert*() functions ([2a16699](https://github.com/php-testo/testo/commit/2a166992e76226ebf8cc6334f9a3bcef1c0abc93))
* **bridge-rector:** fold exception modifiers across plain assignments ([39eb232](https://github.com/php-testo/testo/commit/39eb232308113fbf66d57c8fbb36bd3ab3ad757e))
* **bridge-rector:** keep testo-polish from adding tests or finalizing extended classes ([b2f6bf2](https://github.com/php-testo/testo/commit/b2f6bf29c3b648f782d37dec196ae4ee59482769))
* **bridge-rector:** let converted with() accept extra call arguments like PHPUnit ([662e6e5](https://github.com/php-testo/testo/commit/662e6e5f05428a7f8110ddfdffa56077934ba8f9))
* **bridge-rector:** mark the test methods of traits ([cb620f4](https://github.com/php-testo/testo/commit/cb620f4300d02ca2cc32f186ad06935c9db75519))
* **bridge-rector:** run the phpunit-to-testo set serially ([ca813ee](https://github.com/php-testo/testo/commit/ca813ee8a7e561d4f20818fefc76cb0cf31dfc9e))


### Code Refactoring

* **assert:** rename the regex checks to `matchesRegex()` and deprecate `withMessagePattern()` ([fec5525](https://github.com/php-testo/testo/commit/fec5525bfad842c10017b1d295a71534fab7213d))

## [0.3.1](https://github.com/php-testo/testo/compare/bridge-rector-0.3.0...bridge-rector-0.3.1) (2026-09-25)


### Features

* **bridge-rector:** convert expectExceptionMessageMatches to withMessagePattern ([d409881](https://github.com/php-testo/testo/commit/d40988123cd1da485cbc059bfec10830f940da52))
* **bridge-rector:** log fixture runner system errors to the `rector-errors.json` channel ([b498d28](https://github.com/php-testo/testo/commit/b498d28699910d2f05024ec4572f5b0c05dd0d87))


### Bug Fixes

* **bridge-rector:** add lifecycle attributes only in TestCase classes and traits ([03dd7ef](https://github.com/php-testo/testo/commit/03dd7ef5be5cde852a822c9fa9bfca974042b244))
* **bridge-rector:** convert expectExceptionMessage to withMessageContaining ([d409881](https://github.com/php-testo/testo/commit/d40988123cd1da485cbc059bfec10830f940da52))
* **bridge-rector:** convert withMessageContaining back to expectExceptionMessage ([d409881](https://github.com/php-testo/testo/commit/d40988123cd1da485cbc059bfec10830f940da52))
* **bridge-rector:** drop `#[\Override]` left dangling after detaching from `TestCase` ([#353](https://github.com/php-testo/testo/issues/353)) ([29fa870](https://github.com/php-testo/testo/commit/29fa870999eda542726401d5655556b0a501d0a1))
* **bridge-rector:** mark the tests of a class that extends TestCase through a base ([2f79fb2](https://github.com/php-testo/testo/commit/2f79fb254dcfaee58eff51a3fb0ac9b41dfc8dea))
* **bridge-rector:** surface Rector system errors in the fixture runner ([#355](https://github.com/php-testo/testo/issues/355)) ([b498d28](https://github.com/php-testo/testo/commit/b498d28699910d2f05024ec4572f5b0c05dd0d87))

## [0.3.0](https://github.com/php-testo/testo/compare/bridge-rector-0.2.7...bridge-rector-0.3.0) (2026-09-25)


### ⚠ BREAKING CHANGES

* **bridge-rector:** split mock conversions into PHPUnit→Double, PHPUnit→Mockery and Mockery→Double sets ([#351](https://github.com/php-testo/testo/issues/351))
* **bridge-rector:** `phpunit-to-testo` no longer converts mocks, since the target library is a choice; add `TestoRectorSetList::PHPUNIT_TO_DOUBLE` or `PHPUNIT_TO_MOCKERY` next to it. `CreateMockToDoubleRector` moved to the `Testo\Bridge\Rector\PhpunitToDouble` namespace.

### Features

* **bridge-rector:** convert PHPUnit mocks onto Mockery ([3da4055](https://github.com/php-testo/testo/commit/3da40553c14ac1070b37a3d0a65b893aa2f7f985))
* **bridge-rector:** split mock conversions into PHPUnit→Double, PHPUnit→Mockery and Mockery→Double sets ([#351](https://github.com/php-testo/testo/issues/351)) ([3da4055](https://github.com/php-testo/testo/commit/3da40553c14ac1070b37a3d0a65b893aa2f7f985))
* **codecov:** let a plugin scope a test's coverage through `CoverageScope` ([#352](https://github.com/php-testo/testo/issues/352)) ([7ec3965](https://github.com/php-testo/testo/commit/7ec3965acc66487941c8de551fe343631cbde851))

## [0.2.7](https://github.com/php-testo/testo/compare/bridge-rector-0.2.6...bridge-rector-0.2.7) (2026-09-19)


### Features

* **bridge-rector:** convert `#[Skip]` in all three directions ([#340](https://github.com/php-testo/testo/issues/340)) ([6168dca](https://github.com/php-testo/testo/commit/6168dca93cd949c60de4214274b8a348d2939f48))

## [0.2.6](https://github.com/php-testo/testo/compare/bridge-rector-0.2.5...bridge-rector-0.2.6) (2026-09-14)


### Features

* **bridge-double:** support Double 0.8.0 ([de046dd](https://github.com/php-testo/testo/commit/de046dd82ccfc688bc7910944b2a35366d1ee8d2))
* **bridge-rector:** convert PHPUnit mocks onto the Double bridge ([#332](https://github.com/php-testo/testo/issues/332)) ([de046dd](https://github.com/php-testo/testo/commit/de046dd82ccfc688bc7910944b2a35366d1ee8d2))


### Bug Fixes

* **rector:** keep node position when stripping class-level #[Test] ([f887f5d](https://github.com/php-testo/testo/commit/f887f5dbdec7ad54484dab9bdd0f87e46650a986))

## [0.2.5](https://github.com/php-testo/testo/compare/bridge-rector-0.2.4...bridge-rector-0.2.5) (2026-08-27)


### Bug Fixes

* **bench:** partition the variance bands cleanly at frstdev == 10.0 ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))
* **rector:** resolve rule instances via findByContract, require rector/rector ^2.6.4 (Rector 2.6.4 dropped RectorConfig::tagged(); rectorphp/rector[#9870](https://github.com/php-testo/testo/issues/9870)) ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))
* **rector:** surface a rule setup failure as an errored data set ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))


### Code Refactoring

* **codecov:** null-coalescing assignment in CoberturaReport ([e0ff9ac](https://github.com/php-testo/testo/commit/e0ff9ac7e21d419d0985c642972331cf4852d6f3))

## [0.2.4](https://github.com/php-testo/testo/compare/bridge-rector-0.2.3...bridge-rector-0.2.4) (2026-08-18)


### Features

* **assert:** implement numeric(), notBlank() and sameElementsAs() ([5cc3e53](https://github.com/php-testo/testo/commit/5cc3e5392c0e08beb67df068e8f5e89aba25d282))
* **rector:** convert new assertions and add Repeat/Retry ([5cc3e53](https://github.com/php-testo/testo/commit/5cc3e5392c0e08beb67df068e8f5e89aba25d282))


### Bug Fixes

* **test:** make the PHPUnit mutation mirror run green under PHPUnit 13.3 ([5cc3e53](https://github.com/php-testo/testo/commit/5cc3e5392c0e08beb67df068e8f5e89aba25d282))


### Documentation

* FEATURE_PARITY.md, both TODO.md, and the testo-write-tests / testo-migrate-from-phpunit skills. ([5cc3e53](https://github.com/php-testo/testo/commit/5cc3e5392c0e08beb67df068e8f5e89aba25d282))

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
