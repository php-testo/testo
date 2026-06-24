# Changelog

## [0.10.27](https://github.com/php-testo/testo/compare/0.10.26...0.10.27) (2026-06-24)


### Features

* add sorting functionality for cases and tests using user-defined comparators ([6c95720](https://github.com/php-testo/testo/commit/6c957200a8df317e3c5b1af1e7bfb52167101969))


### Bug Fixes

* **filter:** match inherited tests when filtering by name ([e7c33f5](https://github.com/php-testo/testo/commit/e7c33f559585a7f073f662f686ae2c2e3bde5223))
* **teamcity:** attribute inherited tests to the concrete class ([b03557e](https://github.com/php-testo/testo/commit/b03557efef92c74c3a892c25cb4166958b553b82))
* **teamcity:** render previous throwable message in failure details ([e8688b3](https://github.com/php-testo/testo/commit/e8688b33e6c9e1953d9d8652879604b2091342dd))

## [0.10.26](https://github.com/php-testo/testo/compare/0.10.25...0.10.26) (2026-06-21)


### Bug Fixes

* **junit:** attribute inherited tests to the concrete class ([#231](https://github.com/php-testo/testo/issues/231)) ([fe81392](https://github.com/php-testo/testo/commit/fe8139215b98d018dfc3608af01867a8d6c79df5))

## [0.10.25](https://github.com/php-testo/testo/compare/0.10.24...0.10.25) (2026-06-20)


### Bug Fixes

* **tokenizer:** stop misregistering closures and anonymous-class methods ([d7f8ad9](https://github.com/php-testo/testo/commit/d7f8ad9a8bddeeb039753a785fd3b50eca1adff4))


### Performance Improvements

* **tokenizer:** tokenize with PhpToken instead of token_get_all ([#230](https://github.com/php-testo/testo/issues/230)) ([a2262ad](https://github.com/php-testo/testo/commit/a2262ad61c27e4d1bb985748be187c965a2baeae))

## [0.10.24](https://github.com/php-testo/testo/compare/0.10.23...0.10.24) (2026-06-19)


### Features

* **assert:** add `notContains` assertion to iterable type ([c320fbf](https://github.com/php-testo/testo/commit/c320fbf1501911dcc0c079a68039531a730eee99))
* **filter:** filtering with `#[Group]` attr and `--group` flag ([#227](https://github.com/php-testo/testo/issues/227)) ([624b9ab](https://github.com/php-testo/testo/commit/624b9ab877b5d4e0c24c2e594ac4da1890182df1))
* **filter:** support CLI options/arguments/env in `TestingSuite` ([624b9ab](https://github.com/php-testo/testo/commit/624b9ab877b5d4e0c24c2e594ac4da1890182df1))
* **output:** add JSON output plugin with `--json` and `--log-json` flags ([#228](https://github.com/php-testo/testo/issues/228)) ([abb91b3](https://github.com/php-testo/testo/commit/abb91b37a41ec1c10a28a8c0802a1c55411956a4))
* **teamcity:** emit test description as TeamCity metainfo on testStarted ([75fee02](https://github.com/php-testo/testo/commit/75fee0229a1e76912ab19c3a90de5a499aa41e8b))


### Documentation

* **skills:** add `testo-mutation-testing` skill ([273ad95](https://github.com/php-testo/testo/commit/273ad95cbcad40a1221b707983488e464aaeb22b))
* **skills:** clarify Group attribute namespace in migrate-from-phpunit skill ([02401cf](https://github.com/php-testo/testo/commit/02401cfc7e66e25c8e69dd4fbbcce80a773c8244))


### Code Refactoring

* **output:** coalesce consecutive same-channel output in JSON report ([bec606b](https://github.com/php-testo/testo/commit/bec606b9dec8ac3fd045704569a115b9d71f89d9))

## [0.10.23](https://github.com/php-testo/testo/compare/0.10.22...0.10.23) (2026-06-11)


### Bug Fixes

* add `psr/log` dependency for messenger ([d02ec96](https://github.com/php-testo/testo/commit/d02ec96d2dc6971b8ce6e841cc4f8ffd44ec8f5d))

## [0.10.22](https://github.com/php-testo/testo/compare/0.10.21...0.10.22) (2026-06-10)


### Features

* Add Facade package ([50fe007](https://github.com/php-testo/testo/commit/50fe0070205b3f676f645e58e35df7d86e15b689))

## [0.10.21](https://github.com/php-testo/testo/compare/0.10.20...0.10.21) (2026-06-09)


### Bug Fixes

* **codecov:** detect Xdebug coverage mode via xdebug_info('mode') ([703f9ca](https://github.com/php-testo/testo/commit/703f9ca54d29d15add1a32733a4964f81e4d3f89))

## [0.10.20](https://github.com/php-testo/testo/compare/0.10.19...0.10.20) (2026-06-07)


### Bug Fixes

* **codecov:** enforce --coverage driver requirement during configuration ([1e143d3](https://github.com/php-testo/testo/commit/1e143d309b1cd24b2fccf6c0db39c03c1b30e0a1))

## [0.10.19](https://github.com/php-testo/testo/compare/0.10.18...0.10.19) (2026-06-07)


### Code Refactoring

* **messenger:** Move messenger into Core ([0ac2728](https://github.com/php-testo/testo/commit/0ac272898b9e24e240f1e301573c8102abbc2ce0))

## [0.10.18](https://github.com/php-testo/testo/compare/0.10.17...0.10.18) (2026-06-06)


### Bug Fixes

* **release:** pin testo/testo with open upper bound to resolve on dev branches ([c9b4762](https://github.com/php-testo/testo/commit/c9b4762bb9331e022f6428c73a715f17cbc36cf8))


### Code Refactoring

* **core:** Change DTO classes to readonly ([1f3e49b](https://github.com/php-testo/testo/commit/1f3e49bba85a3a7b87d6c91bebc3c338276d4298))

## [0.10.17](https://github.com/php-testo/testo/compare/0.10.16...0.10.17) (2026-06-06)


### Features

* **core:** aggregate run statistics via Summary DTO and show assertions ([cd20c42](https://github.com/php-testo/testo/commit/cd20c42e034376721c820dc34d628ba5c1598f63))
* **coverage:** add CLI flags for Clover, Cobertura, and XML coverage reports ([#204](https://github.com/php-testo/testo/issues/204)) ([f87365b](https://github.com/php-testo/testo/commit/f87365bacb1e1dab1f909258b698b28236f48bb1))
* **testing:** implement property autowiring with `Inject` attribute and `InjectPlugin` ([0915a3f](https://github.com/php-testo/testo/commit/0915a3f0d6969b4b477278e023c6e4d5f44ae63b))


### Bug Fixes

* **repeat:** ensure log messages end with a newline for clarity ([a549ce7](https://github.com/php-testo/testo/commit/a549ce7a68cacace7a507f599cb5cc0e34d7e014))


### Code Refactoring

* **teamcity:** Disable rendering if channel title ([d12228f](https://github.com/php-testo/testo/commit/d12228fdd4bc3ca1604016aad1de9a561315072c))
* **testing:** update TestRunner namespace from Traits to Helper ([14ebce3](https://github.com/php-testo/testo/commit/14ebce3a0191f82e8dbf5ae145dd3c435ef64fd0))

## [0.10.16](https://github.com/php-testo/testo/compare/0.10.15...0.10.16) (2026-06-02)


### Features

* **messenger:** implement fork functionality for mergeable child branches ([341f38a](https://github.com/php-testo/testo/commit/341f38a2e91d86121640842497ca5f749c12fade))
* **repeat:** integrate Messenger into RepeatInterceptor ([fe12ad5](https://github.com/php-testo/testo/commit/fe12ad5b038351d5b4f35de286795caffc2a1b59))
* **retry:** integrate Messenger for logging in right way ([cb7786f](https://github.com/php-testo/testo/commit/cb7786fac73378864a7b36a2da9430d6f8e75392))


### Bug Fixes

* **messenger:** correct package name from 'destroyable' to 'destroy' ([b11b6ee](https://github.com/php-testo/testo/commit/b11b6eebd38fe247c74b09367633efb0ccd202d4))


### Code Refactoring

* **assert:** Migrate Assert plugin to channels ([9a13ff1](https://github.com/php-testo/testo/commit/9a13ff17efea95b4ab8289dea8913b3ec4f96a30))
* **bench:** Send benchmark results into messenger ([e7fa77a](https://github.com/php-testo/testo/commit/e7fa77a8d9a3ae49ab27ddf05a9cbd0d082b389c))
* **messenger:** adjust ORDER constant for output capturing scope ([ea8b1f5](https://github.com/php-testo/testo/commit/ea8b1f52c907cff5b05635f89aef5ed49dee9366))
* **retry:** Disable Messenger forks for this plugin ([e0fbc32](https://github.com/php-testo/testo/commit/e0fbc3264b72529cd6c040e17b75383b8713d900))

## [0.10.15](https://github.com/php-testo/testo/compare/0.10.14...0.10.15) (2026-06-01)


### Features

* **core:** add `Message` DTOs to store test messages ([36e3e32](https://github.com/php-testo/testo/commit/36e3e323d8a338f3c6a1ac987d46514de72a7263))
* introduce Verbosity enum to manage console output levels ([f0359d7](https://github.com/php-testo/testo/commit/f0359d7f6063154042591437e539e160ef7be460))
* **messenger:** Add Messenger plugin ([3e50a92](https://github.com/php-testo/testo/commit/3e50a92a29a3803b7bd09fe97bb9aa922d40a84b))
* **teamcity:** Support real-time message streaming with channel attributes ([39ed14b](https://github.com/php-testo/testo/commit/39ed14baf3de0dde4cc7677d74515ac6dfcae467))
* **terminal:** Support messages stream rendering with verbosity ([b4175e8](https://github.com/php-testo/testo/commit/b4175e8d1727fe7902a012292ff522e16cbd6269))
* **testing:** add support for extra plugins in TestingSuite configuration ([d2eae1c](https://github.com/php-testo/testo/commit/d2eae1c07140763cc9db584cc8b2a0dbf73ff7dc))


### Bug Fixes

* **codecov:** collect per-data-set coverage from MultipleResult child results ([b287f91](https://github.com/php-testo/testo/commit/b287f91d6e8e019a7e8566c6905c20dad0393c23))
* **data:** accumulate data sets across all provider attributes instead of keeping only the last ([b287f91](https://github.com/php-testo/testo/commit/b287f91d6e8e019a7e8566c6905c20dad0393c23))

## [0.10.14](https://github.com/php-testo/testo/compare/0.10.13...0.10.14) (2026-05-27)


### Code Refactoring

* Improve summary format in CLI ([e7f5948](https://github.com/php-testo/testo/commit/e7f59480b4bdc2d65d06f99bbeef15dc19a91ff3))

## [0.10.13](https://github.com/php-testo/testo/compare/0.10.12...0.10.13) (2026-05-27)


### Features

* Add domain exceptions to skip or cancel test ([#191](https://github.com/php-testo/testo/issues/191)) ([6d800e0](https://github.com/php-testo/testo/commit/6d800e04d9c7185c7b48c51c9efce743492b9042))
* **assert:** Expose `Assert::notNull()` ([#189](https://github.com/php-testo/testo/issues/189)) ([6523c6a](https://github.com/php-testo/testo/commit/6523c6ad8b9c9bf3c40d721b4347488f14809bc3))


### Documentation

* Update README to enhance `init` command setup instructions ([f7a8391](https://github.com/php-testo/testo/commit/f7a83916257af9d1cd0670f723301d347297e35a))
* Update README with additional test execution instructions ([6ef9556](https://github.com/php-testo/testo/commit/6ef95564e8afe1d56714f827c35acfd86ef15d5f))

## [0.10.12](https://github.com/php-testo/testo/compare/0.10.11...0.10.12) (2026-05-20)


### Features

* Add `init` command ([#182](https://github.com/php-testo/testo/issues/182)) ([cd940d6](https://github.com/php-testo/testo/commit/cd940d6013d6e2942e8ae2e766d23f7d398313af))


### Bug Fixes

* **symfony-console:** Make init test compatible with Symfony 8 (no `add()`) ([42888e8](https://github.com/php-testo/testo/commit/42888e8be7cc518372ad58bbdcbe3c978e253cd9))
* **symfony-console:** Make init treat --path as the project root ([0f5de95](https://github.com/php-testo/testo/commit/0f5de956cdfd16505060742ee02c6af1f6927606))

## [0.10.11](https://github.com/php-testo/testo/compare/0.10.10...0.10.11) (2026-05-18)


### Bug Fixes

* Allow `infection/include-interceptor` v1 ([#184](https://github.com/php-testo/testo/issues/184)) ([cc7018c](https://github.com/php-testo/testo/commit/cc7018cf5e997b7be9d34e544d581d2deb6781ba))
* **assert:** Add missing psalm/phpstan assert annotations ([#179](https://github.com/php-testo/testo/issues/179)) ([0af9eb0](https://github.com/php-testo/testo/commit/0af9eb082c5a54f79657ff47cfca47ffeef6f0c3))


### Documentation

* Align Test/Test Case/Test Suite terminology across docs, events, and skills ([fa56ade](https://github.com/php-testo/testo/commit/fa56adef60dae79e613e60774ec871c184434a1f))

## [0.10.10](https://github.com/php-testo/testo/compare/0.10.9...0.10.10) (2026-05-14)


### Bug Fixes

* **coverage:** Check for Xdebug coverage mode before initializing driver ([5254038](https://github.com/php-testo/testo/commit/52540384e80dde58697007c4ee42925f72b70b7b))
* **lifecycle:** Remove lifecycle methods from test collection ([268e635](https://github.com/php-testo/testo/commit/268e6352e859ce5b8c3d208dd4b5a2cc8d318da2))


### Documentation

* Add AI Agent skills ([0315d1c](https://github.com/php-testo/testo/commit/0315d1c7fa52df012f50bfe8b0fe7e4ef0587bb9))

## [0.10.9](https://github.com/php-testo/testo/compare/0.10.8...0.10.9) (2026-05-12)


### Features

* **core:** Add ability to remove a test from a test set on locating ([77e50eb](https://github.com/php-testo/testo/commit/77e50eb39a6cd725ff9f9f0532e9767067036dfe))
* **repeat:** Add `maxFailures` option ([#114](https://github.com/php-testo/testo/issues/114)) ([b250922](https://github.com/php-testo/testo/commit/b250922567a47337ce47f4e6f7431b95d56c9179))


### Documentation

* Add AGENTS.md for AI coding agents and update CLAUDE.md links ([849e035](https://github.com/php-testo/testo/commit/849e03500582940c44409162251d61861cfc2b33))
* **repeat:** Clarify documentation for Repeat class and parameters ([2544390](https://github.com/php-testo/testo/commit/25443909fb6f309b61d921796b63d51585d35908))

## [0.10.8](https://github.com/php-testo/testo/compare/0.10.7...0.10.8) (2026-05-10)


### Features

* **assert:** Add `ComparisonFailure` ([46c7045](https://github.com/php-testo/testo/commit/46c704591af17a66f25c4b4e674b6d9c51b5db9a))
* **assert:** Add DIFF into output ([bf41558](https://github.com/php-testo/testo/commit/bf415588296700bbcb56ce08d59b9f1fcd0f9ea6))


### Bug Fixes

* **core:** Repair filtering by multiple files in the same directory ([861923c](https://github.com/php-testo/testo/commit/861923c4bb9965e24723e6487a3d2da538a42571))
* **infection:** enable IncludeInterceptor before requiring composer autoload ([0240517](https://github.com/php-testo/testo/commit/0240517bae0f6178b71f4fc4325c9baa0ace2446))
* **infection:** pass Testo's `--path` values relative to `projectDir` ([c624250](https://github.com/php-testo/testo/commit/c62425072ddd19c134268f490fbf64973481e291))


### Code Refactoring

* **assert:** Remove `AssertException` and use `AssertionException` instead ([b4166f6](https://github.com/php-testo/testo/commit/b4166f65b1832156dbad289525750b2729f11354))

## [0.10.7](https://github.com/php-testo/testo/compare/0.10.6...0.10.7) (2026-05-08)


### Features

* **test:** allow #[Test] attribute on private methods ([86a1dfd](https://github.com/php-testo/testo/commit/86a1dfdbb33e41926ded9f9052139265ca440046))


### Code Refactoring

* **core:** Remove block-annotations from tests description ([c6a1e20](https://github.com/php-testo/testo/commit/c6a1e20ed79489acd0a14e962de52de320a931f0))

## [0.10.6](https://github.com/php-testo/testo/compare/0.10.5...0.10.6) (2026-05-07)


### Features

* **output:** Add `testo:data-*` attributes into JUnit format ([ccb19ed](https://github.com/php-testo/testo/commit/ccb19ed566ecb977dca276394888207a317df95d))
* **output:** Add test type filter to JUnit plugin ([954b3c4](https://github.com/php-testo/testo/commit/954b3c402af77a74881391e48f58260a7887f196))


### Bug Fixes

* **inline:** Repair running of inline tests on a user function ([3866d6d](https://github.com/php-testo/testo/commit/3866d6dc56fb8e8916e37c51cb3e81949805e2cc))
* **output:** Wrap every test function with individual testsuite tag with its namespace in JUnit report ([ccb19ed](https://github.com/php-testo/testo/commit/ccb19ed566ecb977dca276394888207a317df95d))


### Code Refactoring

* **codecov:** Enable covering Inline tests by default again ([4b9bc47](https://github.com/php-testo/testo/commit/4b9bc47491a6b448ae6042986a615f46429f035c))
* **codecov:** Stop covering Inline tests by default because of [#159](https://github.com/php-testo/testo/issues/159) ([94ac94c](https://github.com/php-testo/testo/commit/94ac94c5ce146b08cc1f461e5bb907addb17d620))

## [0.10.5](https://github.com/php-testo/testo/compare/0.10.4...0.10.5) (2026-05-05)


### Features

* Add JUnit output format with CLI flag `--log-junit` ([#122](https://github.com/php-testo/testo/issues/122)) ([c138d1d](https://github.com/php-testo/testo/commit/c138d1dc1481bca85f0851c7ca3e25427a3cecda))
* **infection:** Use JUnit format with fallback to reflection ([248d811](https://github.com/php-testo/testo/commit/248d811345df0dc462f98a5ac5fc5d8ebe98265c))

## [0.10.4](https://github.com/php-testo/testo/compare/0.10.3...0.10.4) (2026-05-02)


### Bug Fixes

* **composer:** Switch testo/* requires from `^1.0@dev` to `0.1 - 1` ([403768f](https://github.com/php-testo/testo/commit/403768f9058cf09bca8c823f76e64530e31e1dc4))

## [0.10.3](https://github.com/php-testo/testo/compare/0.10.2...0.10.3) (2026-05-02)


### Features

* Add Infection bridge ([#126](https://github.com/php-testo/testo/issues/126)) ([3229bcc](https://github.com/php-testo/testo/commit/3229bcc529bdde8b582bec54361a1ab44c69220c))
* **Assert:** Add `Expect::exception()` strictness mode ([#118](https://github.com/php-testo/testo/issues/118)) ([ee35079](https://github.com/php-testo/testo/commit/ee35079a24e02fdd15d2235a5396e360a083a06c))
* **Codecov:** Add PHPUnit XML report format ([#124](https://github.com/php-testo/testo/issues/124)) ([604af51](https://github.com/php-testo/testo/commit/604af51245448d567382938b18a2a5b6e1777aa8))


### Bug Fixes

* **Tokenizer:** Fixed methods detecting in the `getMethodsFQN` method ([323d815](https://github.com/php-testo/testo/commit/323d815ee8d0796065e0c61ce71acb890b71d75a))


### Documentation

* **repeat:** Update README with project details and usage instructions ([eac870d](https://github.com/php-testo/testo/commit/eac870df54d381ad181b94af855c56acd4fdc895))
* **spec:** Add guide for creating new plugins ([219ecb6](https://github.com/php-testo/testo/commit/219ecb62931cf49ecf4f0b5eca69867177ebd482))


### Code Refactoring

* **assert:** Move Assert plugin in a separated repository ([12d2796](https://github.com/php-testo/testo/commit/12d27966971e2451286bdd224d0f19d8e6d27b06))
* **bench:** Move Bench plugin in a separated repository ([fd0fd73](https://github.com/php-testo/testo/commit/fd0fd735c164de256289b902e1227d4bcf653036))
* **bridge-symfony-console:** Rename Symfony Console bridge ([0261f06](https://github.com/php-testo/testo/commit/0261f0641e7650cf92f5a3306bc5706bf8dcdf0b))
* **bridge-symfony:** Move Symfony bridge in a separated repository ([0d5d1c9](https://github.com/php-testo/testo/commit/0d5d1c9fe17a93046d77388586f1cf7b1e50da84))
* **codecov:** Move Codecov plugin in a separated repository ([63983b1](https://github.com/php-testo/testo/commit/63983b13b811a50ddbe5dfbbca3630105d75700d))
* **Codecov:** Simplify PhpUnitXmlReport ([180f3cb](https://github.com/php-testo/testo/commit/180f3cbac7082cd991ca8d216554c5d1bc2d3f80))
* **convention:** Move Convention plugin in a separated repository ([1a4044f](https://github.com/php-testo/testo/commit/1a4044f0390d3cd16964f47b069471d7a5c9a3b6))
* **data:** Move Data plugin in a separated repository ([8f3d971](https://github.com/php-testo/testo/commit/8f3d971a331db5e3d6bfb911a5f941e75d498945))
* **filter:** Move Filter plugin in a separated repository ([651a761](https://github.com/php-testo/testo/commit/651a76145a5833716a042dec19cbee2c01c67208))
* **inline:** Move Inline plugin in a separated repository ([4e2342c](https://github.com/php-testo/testo/commit/4e2342c9cefc0b3c92a7570ed02b3369f3ad6206))
* **lifecycle:** Move Lifecycle plugin in a separated repository ([cb0ea16](https://github.com/php-testo/testo/commit/cb0ea1656137d4406ac843c921aa1ff1ad4997a8))
* Move all php files into `core` dir ([743cb4f](https://github.com/php-testo/testo/commit/743cb4f42d7e6a8bcd5c3ca467b03aa8236cd645))
* Normalize branch-aliases ([6fd048c](https://github.com/php-testo/testo/commit/6fd048c31fa46080c68aad61f518fb4bf564e8b9))
* Normalize Test Suite naming ([9fd0cd3](https://github.com/php-testo/testo/commit/9fd0cd33f1ce6e6a49332be53d98bf6f78a15217))
* **output-teamcity:** Move Output/Teamcity back into core ([9558d46](https://github.com/php-testo/testo/commit/9558d466dfdb94ff2f08a48ac16a4706efc2baa2))
* **output-teamcity:** Move Output/Teamcity plugin in a separated repository ([1db6c20](https://github.com/php-testo/testo/commit/1db6c20b63f86f880a98e047ef2340827d059bdc))
* **repeat:** Move Repeat plugin in a separated repository ([a3bf466](https://github.com/php-testo/testo/commit/a3bf466a5ed743e747718f64d95f139557b311f0))
* **retry:** Move Retry plugin in a separated repository ([0fa769e](https://github.com/php-testo/testo/commit/0fa769ec9e570e6ed57f195d15ca9e3db3851e47))
* **test:** Move Test plugin in a separated repository ([21033ca](https://github.com/php-testo/testo/commit/21033cadbbf23f10aa660fc15f00c68b9a67a59c))

## [0.10.2](https://github.com/php-testo/testo/compare/0.10.1...0.10.2) (2026-04-07)


### Features

* **Assert:** Add `::between` assertion method for numeric values ([6b18d6e](https://github.com/php-testo/testo/commit/6b18d6e531f82ea5eb9161ff005b38ac3bee2000))
* **Codecov:** Add `Covers` and `CoversNothing` attributes ([#110](https://github.com/php-testo/testo/issues/110)) ([048ff91](https://github.com/php-testo/testo/commit/048ff9100c91bd23d9e1d65d0426e6846adcd081))
* **Codecov:** Add Codecov plugin ([#109](https://github.com/php-testo/testo/issues/109)) ([add5065](https://github.com/php-testo/testo/commit/add5065588e7737140102ce512734364d8a2d307))
* **Repeat:** Expose `#[Repeat] attribute ([#113](https://github.com/php-testo/testo/issues/113)) ([38e8d29](https://github.com/php-testo/testo/commit/38e8d29105b8ea5e20b485944e9bca8ccc92b1a4))


### Bug Fixes

* **Bench:** Fix running functions ([12daa52](https://github.com/php-testo/testo/commit/12daa52a9b3b44134eef5ec1a859a1e4cf0b0604))
* **Repeat:** Stop repeating on Cancelled/Skipped/Aborted statuses ([2fdce34](https://github.com/php-testo/testo/commit/2fdce34a6f36bb908e51907e65709ca524857e3e))


### Documentation

* **Assert:** Add notes to interfaces indicating they are not for userland implementation ([e13d2c2](https://github.com/php-testo/testo/commit/e13d2c2518aeeec05e1e7f1518ef282b1a215376))


### Code Refactoring

* **Container:** Stop cloning enums; add `destroy` option to `Container::set()` method ([add5065](https://github.com/php-testo/testo/commit/add5065588e7737140102ce512734364d8a2d307))
* **Repeat:** Change Retry/Repeat/Assert interceptors priority; polish the code ([38e8d29](https://github.com/php-testo/testo/commit/38e8d29105b8ea5e20b485944e9bca8ccc92b1a4))

## [0.10.1](https://github.com/php-testo/testo/compare/0.10.0...0.10.1) (2026-03-22)


### Features

* **Assert:** Implement JSON assertion features ([#106](https://github.com/php-testo/testo/issues/106)) ([25bf75c](https://github.com/php-testo/testo/commit/25bf75c7a1c2bdfad49b751be831e45080aa3f18))
* Implement `Expect::exception()` methods ([#104](https://github.com/php-testo/testo/issues/104)) ([8b89874](https://github.com/php-testo/testo/commit/8b8987412dcb87cdaec963c82509b6e58675f1f4))


### Code Refactoring

* **Assert:** Change return type from `self` to `static` in exception handling methods ([6b25254](https://github.com/php-testo/testo/commit/6b25254654841a877a2318955139befcd426e398))

## [0.10.0](https://github.com/php-testo/testo/compare/0.9.0...0.10.0) (2026-03-17)


### ⚠ BREAKING CHANGES

* **Test:** Remove `description` parameter from the `#[Test]` attribute
* **Lifecycle:** Rename attributes to be more clear
* Remove `$parallel` flag from `SuiteConfig`
* Rename `\Testo\Retry\RetryPolicy` to `\Testo\Retry`
* Prepare moving `ObjectContainer` into `internal/container` package
* Change namespaces for many classes
* **Bench:** Move `#[Bench]` into `Testo` namespace
* Change namespaces for `PluginConfigurator`, `Filter`
* **Test:** `#[AssertMethod]` attribute moved to the `Testo\Common\Attribute` namespace
* **Test:** `#[Test]` attribute moved to the `Testo` namespace

### Features

* **Test:** `#[Test]` attribute finder is a plugin now ([7987dc7](https://github.com/php-testo/testo/commit/7987dc7fc09b0d373a940e63603950d6a07e5436))


### Bug Fixes

* Resolve decrement on type `null` ([9c79e00](https://github.com/php-testo/testo/commit/9c79e0038a4c8d0f9b4ac670502789d1863fed5a))
* **Test:** Detect test methods with 'never' return type in annotated class ([e2eafbd](https://github.com/php-testo/testo/commit/e2eafbd1f7702771df05202d56bfc89c8c146756))


### Code Refactoring

* **Bench:** Move `#[Bench]` into `Testo` namespace ([46c46bb](https://github.com/php-testo/testo/commit/46c46bbe8701df5e74d33199a8204f2566679530))
* Change namespaces for `PluginConfigurator`, `Filter` ([53808e8](https://github.com/php-testo/testo/commit/53808e88e06b17654c97d6f1b940331272a51913))
* Change namespaces for many classes ([7a24e94](https://github.com/php-testo/testo/commit/7a24e945292339ef50ae63ca42475c2768ac639b))
* **Filter:** Filter functionality separated to a plugin ([53808e8](https://github.com/php-testo/testo/commit/53808e88e06b17654c97d6f1b940331272a51913))
* **Lifecycle:** Rename attributes to be more clear ([aa314b3](https://github.com/php-testo/testo/commit/aa314b3dc98278547a32837e1d658035b1e587cb))
* Prepare moving `ObjectContainer` into `internal/container` package ([0728069](https://github.com/php-testo/testo/commit/0728069423cf368c5614429c94a4db74468ed9ac))
* Remove `$parallel` flag from `SuiteConfig` ([27fefa8](https://github.com/php-testo/testo/commit/27fefa8ba6d28dda197d16a7b7ef37c7dff4069f))
* Rename `\Testo\Retry\RetryPolicy` to `\Testo\Retry` ([8ad754b](https://github.com/php-testo/testo/commit/8ad754b1c12ec444a9b2eefe357c99b8dc8d44aa))
* **Test:** `#[AssertMethod]` attribute moved to the `Testo\Common\Attribute` namespace ([277d2c4](https://github.com/php-testo/testo/commit/277d2c40b4f473b1e156d859fb789ccb37f624e5))
* **Test:** `#[Test]` attribute moved to the `Testo` namespace ([7987dc7](https://github.com/php-testo/testo/commit/7987dc7fc09b0d373a940e63603950d6a07e5436))
* **Test:** Remove `description` parameter from the `#[Test]` attribute ([4667abd](https://github.com/php-testo/testo/commit/4667abd81b0c4cb94548bc1fc5b29f5108b135db))

## [0.9.0](https://github.com/php-testo/testo/compare/0.8.1...0.9.0) (2026-03-14)


### ⚠ BREAKING CHANGES

* **Assert:** Use ACTUAL, EXPECTED order in assertions

### Features

* **Assert:** Add `Assert::count()` method ([16dfa0f](https://github.com/php-testo/testo/commit/16dfa0fc707fffa106c1887654670e01908d3eda))
* **Assert:** All the assertion and expectation exceptions are extended from `LogicException` now ([#96](https://github.com/php-testo/testo/issues/96)) ([8c1a510](https://github.com/php-testo/testo/commit/8c1a510c19f41722aaa1c19941402b2542125127))
* **Assert:** Assertion feature is a plugin now ([365b52f](https://github.com/php-testo/testo/commit/365b52ff668d72d9fa096ac62f50b0b0c09e55e1))
* **Config:** Support arrays instead of `FinderConfig` in `ApplicationConfig` and `SuiteConfig` ([df26e30](https://github.com/php-testo/testo/commit/df26e30cc1d9866a8afd688d6657aebb3a1bf607))


### Bug Fixes

* **Inline:** Respect filtering DataProvider options ([8656891](https://github.com/php-testo/testo/commit/8656891a73df2f8f52af9d9fdac2ee87ee14a0ae))
* **Teamcity:** Send full DataSet coordinates in FQN ([8656891](https://github.com/php-testo/testo/commit/8656891a73df2f8f52af9d9fdac2ee87ee14a0ae))


### Code Refactoring

* **Assert:** Use ACTUAL, EXPECTED order in assertions ([b606c3d](https://github.com/php-testo/testo/commit/b606c3daaa0ff06273536b37a7878ef6549c7c78))
* **Filter:** Add properties normalization to absolute Path ([e3a2e8e](https://github.com/php-testo/testo/commit/e3a2e8e04ea5b2b5c68188507bea5bbb1591bde9))

## [0.8.1](https://github.com/php-testo/testo/compare/0.8.0...0.8.1) (2026-03-11)


### Features

* **Application:** Add `plugins` into `SuiteConfig` ([82f7d8c](https://github.com/php-testo/testo/commit/82f7d8c5df23f4042725950b7a8f96c945065be2))
* **Bench:** Benchmarking feature is a plugin now ([ea05797](https://github.com/php-testo/testo/commit/ea0579712d6c3587e0452232847eb767b436d283))
* **Config:** Add plugin configuration system with defaults management. ([2380d15](https://github.com/php-testo/testo/commit/2380d15930c8d16fc8dc3df7b097ae1f8c060a5e))
* **Container:** Add `scope()` method ([8e6697a](https://github.com/php-testo/testo/commit/8e6697a2ed5b6b4f305db15f507d4af1375ed6dd))
* **Convention:** Move tests prefix-suffix finder into the new `Convention` plugin ([e09c90c](https://github.com/php-testo/testo/commit/e09c90cc0f3b1e77a5621def064dd515d6737342))
* **Inline:** Inline Tests feature is a plugin now ([09b166b](https://github.com/php-testo/testo/commit/09b166b44f0bd1c6bed07a318a56d319bc954c1d))
* **Lifecycle:** Lifecycle feature is a plugin now ([dd9e483](https://github.com/php-testo/testo/commit/dd9e4839862c9471fa69ba4d5ff85b1043710732))
* **Pipeline:** Make `Interceptor` interface public ([2bebcd8](https://github.com/php-testo/testo/commit/2bebcd896cc5879eb1cc3408061e376ae0027077))


### Code Refactoring

* **Application:** Run each test suite in an isolated container scope ([aa29419](https://github.com/php-testo/testo/commit/aa294190051d8301e246aaafc482fbba731492fc))
* **Core:** Rename Test Invoker into Test Handler ([82f7d8c](https://github.com/php-testo/testo/commit/82f7d8c5df23f4042725950b7a8f96c945065be2))
* **Filter:** Filter by path before scanning FS ([f11130a](https://github.com/php-testo/testo/commit/f11130a21d1b1be3f62112e69d219f5f68bff476))

## [0.8.0](https://github.com/php-testo/testo/compare/0.7.2...0.8.0) (2026-03-07)


### ⚠ BREAKING CHANGES

* Move `#[Test]` attribute into `Testo\Attribute` namespace

### Features

* Add `#[AssertMethod]` attribute ([#99](https://github.com/php-testo/testo/issues/99)) ([5623710](https://github.com/php-testo/testo/commit/562371045b248e81bd0bb03f0269d8c7c48fbe95))


### Code Refactoring

* Move `#[Test]` attribute into `Testo\Attribute` namespace ([5623710](https://github.com/php-testo/testo/commit/562371045b248e81bd0bb03f0269d8c7c48fbe95))
* Move Terminal and Teamcity renderers into Output namespace ([5623710](https://github.com/php-testo/testo/commit/562371045b248e81bd0bb03f0269d8c7c48fbe95))

## [0.7.2](https://github.com/php-testo/testo/compare/0.7.1...0.7.2) (2026-03-05)


### Features

* **Bench:** Add `warmup` and reporting; update statistics and reasoning ([44a9f75](https://github.com/php-testo/testo/commit/44a9f75b8e420689308dc910d92b283e721c88c6))
* **Bench:** Add Attributed benchmarks PoC ([#92](https://github.com/php-testo/testo/issues/92), [#94](https://github.com/php-testo/testo/issues/94)) ([60b5201](https://github.com/php-testo/testo/commit/60b5201770ac902916e63a1f8a43f0e53baa78c7))
* **CLI:** Add filtering by test type (flag `--type`) ([2aa9b05](https://github.com/php-testo/testo/commit/2aa9b05c2428567201cf9699b49cc51903f915aa))
* **Framework:** Add Session and Worker events ([a663b71](https://github.com/php-testo/testo/commit/a663b710a5fb64183c8ef9a6b6925e715300c180))
* **Maintenance:** Bump min PHP version to 8.2 ([7cbf6d3](https://github.com/php-testo/testo/commit/7cbf6d398461dd7b71d951dbe31f9f17cf375286))


### Code Refactoring

* **CLI:** Print environment info ([a663b71](https://github.com/php-testo/testo/commit/a663b710a5fb64183c8ef9a6b6925e715300c180))
* **Core:** Add `testType` filter option into `InterceptorOptions` ([2aa9b05](https://github.com/php-testo/testo/commit/2aa9b05c2428567201cf9699b49cc51903f915aa))
* **Core:** Group tests by types. So, different test types can be used on the same method. ([a65e1cb](https://github.com/php-testo/testo/commit/a65e1cbd9098c629956d5f683aa6c959f6d4367d))
* **Teamcity:** Render environment info ([a663b71](https://github.com/php-testo/testo/commit/a663b710a5fb64183c8ef9a6b6925e715300c180))

## [0.7.1](https://github.com/php-testo/testo/compare/0.7.0...0.7.1) (2026-02-01)


### Features

* **Common:** Add `Reflection::findMethodsWithAttribute()` ([c0dec74](https://github.com/php-testo/testo/commit/c0dec7424799b33ffc665b7c64d3d729c94be613))
* **Data:** `DataZip` now accepts any Data source ([a771833](https://github.com/php-testo/testo/commit/a77183308ff27b7435c3b0f31a67f961b372c133))
* **Data:** Add `DataUnion` attribute ([d8a0400](https://github.com/php-testo/testo/commit/d8a040037ecc2d2be654eb24e35e09dd003effb0))
* **Data:** Add `DataZip` and `DataCross` attributes ([#93](https://github.com/php-testo/testo/issues/93)) ([5b42e7c](https://github.com/php-testo/testo/commit/5b42e7c0e0e786f3ae5982ab0ce85a610ddff65b))
* **Data:** New behavior for `DataZip`: if the providers have different lengths, the resulting data sets will be as many as the shortest provider. ([d2d13b7](https://github.com/php-testo/testo/commit/d2d13b70d605ceb60809fe6c8bbf6207c817ef8d))
* **Lifecycle:** Add `Before*` and `After*` attributes ([#90](https://github.com/php-testo/testo/issues/90)) ([f60c9bf](https://github.com/php-testo/testo/commit/f60c9bf5aaeb584b5b8f2ad21831c2cb24042e2c))
* **Locators:** Enhance tests detection by name convention, support functions. ([83fdc27](https://github.com/php-testo/testo/commit/83fdc2760e67ef8c8a953656e68f00ddeff78292))
* **Reflection:** Add method `getAttributesFromCallStack()` to retrieve attributes from the call stack ([2c0f790](https://github.com/php-testo/testo/commit/2c0f79083fca100c118beedaa15919afc60a492e))
* **Reflection:** Method `getAttributesFromCallStack()` can scan classes of called methods ([28af07b](https://github.com/php-testo/testo/commit/28af07b64c8e798b4c976ba8ba0688c983e3adce))
* **Testing:** Add `TestRunner` trait with `TestingSuite` attribute ([#36](https://github.com/php-testo/testo/issues/36)) ([da25a2a](https://github.com/php-testo/testo/commit/da25a2a4848b38d20fc9eaf462385d46a533afec))


### Code Refactoring

* **Application:** Accept only public methods with `void` return type when `#[Test]` is used for class ([#33](https://github.com/php-testo/testo/issues/33)) ([4c2f4a2](https://github.com/php-testo/testo/commit/4c2f4a225e8c30d5441e0a116d66e15bd3eb1244))

## [0.7.0](https://github.com/php-testo/testo/compare/0.6.6...0.7.0) (2026-01-18)


### ⚠ BREAKING CHANGES

* Restructure code

### Bug Fixes

* Store `invoker` property on `CaseDefinition` cloning ([634e65c](https://github.com/php-testo/testo/commit/634e65cbbddb768ba505f1d9371a22bce1d23636))


### Code Refactoring

* Configure interceptors order values ([634e65c](https://github.com/php-testo/testo/commit/634e65cbbddb768ba505f1d9371a22bce1d23636))
* Restructure code ([d0f537a](https://github.com/php-testo/testo/commit/d0f537a6d02ebcff590ef38c7fd383f07391bd87))

## [0.6.6](https://github.com/php-testo/testo/compare/0.6.5...0.6.6) (2026-01-17)


### Features

* Add `CaseInstance` interface and default implementation ([#78](https://github.com/php-testo/testo/issues/78)) ([754f351](https://github.com/php-testo/testo/commit/754f351e49c7f08f57272fe49fc66697a188ef96))
* **Assert:** Add `AssertArray::doesNotHaveKeys()` method ([58feee0](https://github.com/php-testo/testo/commit/58feee0572df1f4ef2d6ac4e64f2cbbcfb60b90f))
* **Assert:** Add `Iterable->notEmpty()` assertion ([f2d36c1](https://github.com/php-testo/testo/commit/f2d36c1207eb9ec0f4a7df5ed754529fe4ba08be))
* Classes marked with #[Test] now treat all public methods as tests ([#85](https://github.com/php-testo/testo/issues/85)) ([58feee0](https://github.com/php-testo/testo/commit/58feee0572df1f4ef2d6ac4e64f2cbbcfb60b90f))
* **Render:** Always render test description in CLI ([#87](https://github.com/php-testo/testo/issues/87)) ([71a54d8](https://github.com/php-testo/testo/commit/71a54d82187f509b33448584031bd2f21952b612))
* **Sample:** Add `name` parameter to DataSet attribute ([#70](https://github.com/php-testo/testo/issues/70)) ([ff6bd80](https://github.com/php-testo/testo/commit/ff6bd80b8f98d3ae2c0cb2a92f788e92d0d35dd9))


### Documentation

* Add link to the documentation site ([9526c67](https://github.com/php-testo/testo/commit/9526c675dcfa40dc98209144a1d853aad26a1358))

## [0.6.5](https://github.com/php-testo/testo/compare/0.6.4...0.6.5) (2026-01-05)


### Features

* **Filters:** Add DataProvider filtering by provider and dataset indices ([3541116](https://github.com/php-testo/testo/commit/3541116a48cc6e8b7774b25ed3bd61e51bfd275c))
* **Sample:** Add `DataSet` attribute ([#70](https://github.com/php-testo/testo/issues/70)) ([ea0b5ea](https://github.com/php-testo/testo/commit/ea0b5ea41fcda6bcd0d164e441725b87955924cb))
* **Sample:** Support `DataPointer` filter in Data Providers ([cc21b56](https://github.com/php-testo/testo/commit/cc21b56a94cce394e3b8fa730790d9fa78318046))


### Bug Fixes

* **DefinitionLocator:** Include file if other class loaders failed ([0c68d17](https://github.com/php-testo/testo/commit/0c68d17aad4406f8e282f97569ba5217414f3a6a))
* **Teamcity:** Correct order of test finish and warning messages in logger ([50ca951](https://github.com/php-testo/testo/commit/50ca9512ac153e20bc670710f45ef53e9c167e99))

## [0.6.4](https://github.com/php-testo/testo/compare/0.6.3...0.6.4) (2026-01-02)


### Bug Fixes

* Change autoloading paths priority ([#75](https://github.com/php-testo/testo/issues/75)) ([3644595](https://github.com/php-testo/testo/commit/3644595164891e4e37bf2a4e4365e4440fb641a7))

## [0.6.3](https://github.com/php-testo/testo/compare/0.6.2...0.6.3) (2026-01-01)


### Features

* **Teamcity:** Expose `Assertion History` for every test ([4d248b8](https://github.com/php-testo/testo/commit/4d248b88d336b400245b2cca2992bbd7e152d7a3))


### Code Refactoring

* Inject Attributed interceptors into main pipeline with order ([7121981](https://github.com/php-testo/testo/commit/712198114e0ab860c168ff2aa78bf63b47d7c271))

## [0.6.2](https://github.com/php-testo/testo/compare/0.6.1...0.6.2) (2025-12-30)


### Documentation

* **sample:** Add documentation for Sample module ([#63](https://github.com/php-testo/testo/issues/63)) ([d726b0e](https://github.com/php-testo/testo/commit/d726b0e1035759f08d66fb6c2ccd04c8832a6a8a))


### Code Refactoring

* Enhance inline test functionality ([#63](https://github.com/php-testo/testo/issues/63)) ([d726b0e](https://github.com/php-testo/testo/commit/d726b0e1035759f08d66fb6c2ccd04c8832a6a8a))

## [0.6.1](https://github.com/php-testo/testo/compare/0.6.0...0.6.1) (2025-12-30)


### Features

* **assert:** Add `every()` assertion method for iterable ([#59](https://github.com/php-testo/testo/issues/59)) ([70f6b36](https://github.com/php-testo/testo/commit/70f6b3655f33afa7c2a6022f25e08a246a7c2e7c))
* **assert:** implement `AssertObject` methods ([d1b0ad3](https://github.com/php-testo/testo/commit/d1b0ad3fe3503889d22b4fc4f8d6bcd5ae3d14d5))
* **interceptors:** Add InterceptorOptions with order and ConflictPolicy ([0f569e6](https://github.com/php-testo/testo/commit/0f569e68dbe74ca78dbed5c83a7317a1d19f7700))
* **tests:** Implement inline test functionality with TestInline attribute ([a75e8c2](https://github.com/php-testo/testo/commit/a75e8c2969d07c3bcd475f67a3ae91c624a6def7))


### Bug Fixes

* symfony console 8 support ([#60](https://github.com/php-testo/testo/issues/60)) ([6180ca7](https://github.com/php-testo/testo/commit/6180ca7f20a8a289b14705fd07cabe6e0053aa38))

## [0.6.0](https://github.com/php-testo/testo/compare/0.5.0...0.6.0) (2025-12-21)


### Features

* **assert:** Add `CompositeRecord`; ([498b50b](https://github.com/php-testo/testo/commit/498b50bc2c199642fa3e66fc6b211149f8f2e6bb))
* **assert:** implement `AssertArray` methods ([#57](https://github.com/php-testo/testo/issues/57)) ([c33d236](https://github.com/php-testo/testo/commit/c33d2367a443336b6c3673ea004670f3246043e9))
* **assert:** implement `AssertIterable` methods ([aeb4a1d](https://github.com/php-testo/testo/commit/aeb4a1ddb442d8e8b1ae1c1bce8747484cf83bc6))
* **assert:** implement additional methods for iterable assertions; ([c2df3d8](https://github.com/php-testo/testo/commit/c2df3d83c1fdafdea3dc915d0ad97d7be23767da))


### Code Refactoring

* **assert:** Add `Assertion` interface; ([b49ab89](https://github.com/php-testo/testo/commit/b49ab89ffb9b781b38650e7cc01bfbb526c4ff27))
* **assert:** Add `Expectation` interface; ([c1abbb7](https://github.com/php-testo/testo/commit/c1abbb71dbf40e1358969dfc172e61b1577d3c19))
* **assert:** Normalize interfaces signature and messages ([d213627](https://github.com/php-testo/testo/commit/d213627d18cc7a707381f0e9b150d93ae7290d58))
* **assert:** Replace `AssertTypeSuccess` with `AssertionComposite` in assertion classes ([7d939f6](https://github.com/php-testo/testo/commit/7d939f6f3b8bf8bcae5a27ceccf503f5232e1cf0))
* **assert:** Replace AssertException with Fail in failure handling ([bd37519](https://github.com/php-testo/testo/commit/bd37519c56c29d44e61b33d200d5558b6900207b))
* **assert:** Update namespace for Assertion and Expectation classes; ([f8859b5](https://github.com/php-testo/testo/commit/f8859b511a3585663fd5750702c8510c1694aef6))

## [0.5.0](https://github.com/php-testo/testo/compare/0.4.0...0.5.0) (2025-12-02)


### Features

* Add tests Invoker ([3fd1bdb](https://github.com/php-testo/testo/commit/3fd1bdbd8915c6fba578d6de9128376f26e55a43))


### Bug Fixes

* **TerminalLogger:** add visual output for DataProvider in terminal mode ([275336a](https://github.com/php-testo/testo/commit/275336a745f18c29704e0e27ab573af34cf9f27a))


### Code Refactoring

* rename interceptor classes to renderer for clarity ([81e70e3](https://github.com/php-testo/testo/commit/81e70e3a0c245a13dde506b029ae969982a228d4))
* Use `internal/testo` package instead of local implementation ([fac277b](https://github.com/php-testo/testo/commit/fac277b695bd768f230130dee18858228d8f8cd6))

## [0.4.0](https://github.com/php-testo/testo/compare/0.3.0...0.4.0) (2025-11-18)


### Features

* **App:** Implement plugin configuration system for service bindings ([146b4b5](https://github.com/php-testo/testo/commit/146b4b5458b9b00ebb0a79c4dffe4ded1515a2b2))
* **assert:** add `Assert::blank()` method ([#43](https://github.com/php-testo/testo/issues/43)) ([67bb937](https://github.com/php-testo/testo/commit/67bb937f70490bc8cdd145ef2e7084ef85a80c63))
* **assert:** add `Assert::float`, numeric 'lessThan' and 'lessThanOrEqual' ([#49](https://github.com/php-testo/testo/issues/49)) ([caa677e](https://github.com/php-testo/testo/commit/caa677ec53ae608adddfd794911f02ba15be9ff5))
* **assert:** add `Assert::int()` with `greaterThan` and `greaterThanOrEqual` ([a95ac13](https://github.com/php-testo/testo/commit/a95ac13f86fd7d04db72e2405ffd92565e50e457))
* **assert:** add `Assert::string()`, `Assert::string()->contains()` methods ([010cab1](https://github.com/php-testo/testo/commit/010cab175d9aac830a73e0fb4b60805a34ecb179))
* **Assert:** Add `Expect::leaks` ([14d5802](https://github.com/php-testo/testo/commit/14d5802899ee1e25a0de53bdd0e0c1b191c2f9b7))
* **assert:** Add `notContains()` method to assert string absence ([dba4a12](https://github.com/php-testo/testo/commit/dba4a12dcad36791dcbd6f21fdbba0478f944653))
* **assert:** add `NumericTrait` to store numeric assertions ([683ac2d](https://github.com/php-testo/testo/commit/683ac2d420f2bfefee983f5e30e1263a295dd340))
* **assert:** add AssertJson API ([dc07ba0](https://github.com/php-testo/testo/commit/dc07ba0574655b91e4bae2c3ab23e0b5ecad41ec))
* **assert:** add new assert Interfaces and methods for data types ([61b5e67](https://github.com/php-testo/testo/commit/61b5e670ac18ee93048bc97b7bf11cefb2bb70a8))
* **Assert:** add new assertion methods for array and iterable types ([1b3e43d](https://github.com/php-testo/testo/commit/1b3e43d958bfe07acb217e72eb522ae87ae65f46))
* **assert:** Extract ExpectedException interface; ([6b69628](https://github.com/php-testo/testo/commit/6b6962847e39d543a674b11fb5b810bdf39ae487))
* **assert:** Update `Expect::exception()` API ([da047f5](https://github.com/php-testo/testo/commit/da047f56d2a6c4539903eb48ec60322c8ea6381d))
* **DataProvider:** Support string method names in DataProvider attribute ([e785acd](https://github.com/php-testo/testo/commit/e785acde4a022cd475f7a788ec04b2251e1c576b))
* **Events:** Add Event Dispatcher Implementation ([a3dc17c](https://github.com/php-testo/testo/commit/a3dc17ca50a3ceb9ed88accd5bbf03fd56f9641e))
* **Events:** Add event dispatching for test dataset execution lifecycle ([be43918](https://github.com/php-testo/testo/commit/be439188edb97e9fbebaaeac6c447449276c7665))
* **Events:** Add test case and test suite event classes ([4ff59ce](https://github.com/php-testo/testo/commit/4ff59ce87eb7a59af0ee7321b8ad8075867f58cc))
* **Events:** Add Test events ([d7664d9](https://github.com/php-testo/testo/commit/d7664d94833a26b85baa191c45d2f880daf879e6))
* **Events:** Integrate event dispatching in test and case runners ([94172e6](https://github.com/php-testo/testo/commit/94172e61b078d1eed6d04514f60c6128ed54da9a))
* **Filter:** Add comprehensive filtering options for test execution ([fb7faf6](https://github.com/php-testo/testo/commit/fb7faf60dfb986f491c3671d7ebcbda322e2802a))
* **Filter:** Enhance filtering capabilities with path and name matching ([1107064](https://github.com/php-testo/testo/commit/110706477889159d2318f8d37d2afbab1121e514))
* **Path:** Add wildcard pattern matching ([d027c66](https://github.com/php-testo/testo/commit/d027c6654daae46b8d908dc5fdbc570904d38485))
* **render:** Add stack trace filtering for cleaner error output ([f9239a2](https://github.com/php-testo/testo/commit/f9239a293c4a7550acf2ddabea29185c2f120291))
* **SuiteProvider:** Implement filter functionality ([1111ee1](https://github.com/php-testo/testo/commit/1111ee1e3a017946a386bc2c051e1cfd895d4f17))
* **Tokenizer:** Add `TokenizedFile::getMethodsFQN()` ([0fd3a84](https://github.com/php-testo/testo/commit/0fd3a847e72ff8ca0a88ac10543e8508c2ac066a))


### Bug Fixes

* **assert:** Update type assertion methods to use `validateAndCreate()` ([c6f44ec](https://github.com/php-testo/testo/commit/c6f44ecc4ac0cf26f0b37bba3bcbc211219766c1))
* **renderer:** Add proper DataProvider support in Terminal renderer ([3de7ddd](https://github.com/php-testo/testo/commit/3de7ddd463b728136c3b45b9d853ba74cf671e2e))


### Documentation

* Add documentation about Event System ([58a840b](https://github.com/php-testo/testo/commit/58a840b66b53e6141dc859a92dcda7e70e32e68d))
* Add event naming convention documentation ([b4d5be7](https://github.com/php-testo/testo/commit/b4d5be73de3cbc53d2b7cae705cd5a7b340068f1))
* **cli, filter:** Add CLI and filtering documentation ([5913884](https://github.com/php-testo/testo/commit/59138844479c849cfcefc8eb3ca4c6e01601dcc7))


### Code Refactoring

* **assert:** Change constructors to public and rename `create()` methods to `validateAndCreate()` ([3c61a37](https://github.com/php-testo/testo/commit/3c61a37a94004529932af484c434e275962bd6e8))
* **assert:** Explode interfaces and implementation; ([a344c00](https://github.com/php-testo/testo/commit/a344c00d23117414d418f52bdf319bb47bf3d88d))
* **Assert:** Move `Assert::exception` and `::leaks` methods into `Expect::exception` and `Expect::notLeaks` ([b1e9fb5](https://github.com/php-testo/testo/commit/b1e9fb535cef6a88e9a75e38ad43d1eb88cbae05))
* **Assert:** Polish `Assert::string()` ([5ecb5dc](https://github.com/php-testo/testo/commit/5ecb5dc757a0e134036cbfbf5aaf45cb4e1cf5c8))
* **assert:** Rename `withNoPrevious()` to `withoutPrevious()` ([027f714](https://github.com/php-testo/testo/commit/027f714464639597283884c524e285949d5f8da3))
* **Container:** Fix aliases resolving in container; ([8805e9a](https://github.com/php-testo/testo/commit/8805e9ae0d3e6e10a949d1d1f7e91db92a19a596))
* **Events:** Rename `EventDispatcher`  to `EventListenerDispatcher` ([01bb202](https://github.com/php-testo/testo/commit/01bb202168c30d4206565cee2f4fa30255a054c6))
* **Render:** Add compact throwable formatting in Terminal ([f872a68](https://github.com/php-testo/testo/commit/f872a68f5f39f20169d4f9d8d4f00c9c699e6997))
* **renderer:** Migrate Teamcity renderer to events ([3e5c58d](https://github.com/php-testo/testo/commit/3e5c58dac051c131c2a16922dd7df46f7c5cd00a))
* **renderer:** Migrate Terminal renderer to events ([#46](https://github.com/php-testo/testo/issues/46)) ([23c05bb](https://github.com/php-testo/testo/commit/23c05bbc99c21733c79167a6ecdf864412b090ec))

## [0.3.0](https://github.com/php-testo/testo/compare/0.2.0...0.3.0) (2025-11-02)


### Features

* Add attributes to all test info DTOs ([9fdd418](https://github.com/php-testo/testo/commit/9fdd41838702fa889c081c4ff09ea4dfac4403b0))
* Add DataProvider attribute and interceptor ([e18f8b2](https://github.com/php-testo/testo/commit/e18f8b2277efca75170e92bd71d704cba61db403))
* Add description field to Test attribute ([d00e60d](https://github.com/php-testo/testo/commit/d00e60d0ca03d731e2e1c317964ffb4c67fff732))
* **assert:** Add Fail expectation; ([be1e011](https://github.com/php-testo/testo/commit/be1e011b4bd3a158ae51e61102297685d6ae03b6))
* **assert:** Introduce `StateNotFound` exception for missing test state ([e9e311c](https://github.com/php-testo/testo/commit/e9e311cf022f58856a497bbd034096f911f21200))
* **config:** Add `InflectableConfig` attribute to determine config classes that should be hydrated from user input or configs ([3dc0c93](https://github.com/php-testo/testo/commit/3dc0c93f8c1dad1c65a7d0a41934de389ae184f3))
* make data provider attribute repeatable ([#35](https://github.com/php-testo/testo/issues/35)) ([363602a](https://github.com/php-testo/testo/commit/363602aa2b8a13c1d95388fb280618937f2546fd))


### Documentation

* Add tests/README.md ([3e1faff](https://github.com/php-testo/testo/commit/3e1faffe569b80d26285ca9192f2cb592620f162))
* **readme:** Update installation command, include `--dev` flag ([dddf2b3](https://github.com/php-testo/testo/commit/dddf2b353b339e8e32635550b57702004f124da9))


### Code Refactoring

* **assert:** Convert ObjectTrackerInterceptor into Expectation ([930fb8f](https://github.com/php-testo/testo/commit/930fb8f11aea86751b25af94a3833fcb996127b6))
* **assert:** Return `ExpectedException` from `Assert::exception()` ([3bbaf92](https://github.com/php-testo/testo/commit/3bbaf92b8ddec319fa0e6e5b75edee4e672c2639))
* **assert:** Unhardcode exception expectations ([6855245](https://github.com/php-testo/testo/commit/6855245ca7fb7f364303405bc91af27c4b45d43f))
* **AttributesInterceptor:** map function attributes into TestInfo and CaseInfo ([2e2dee7](https://github.com/php-testo/testo/commit/2e2dee796780d737042237de0b1a35e34d93c1b1))
* **CaseRunner, SuiteRunner:** handle test execution errors ([0d93f08](https://github.com/php-testo/testo/commit/0d93f084ee98d610d0a44464a7b58f0c789b7cfb))
* **formatter:** Add suffix with dataset in Teamcity renderer ([f5d4ca1](https://github.com/php-testo/testo/commit/f5d4ca1c8bd1f3ecd87d831b1ec01fd4e33300f8))
* Rename namespace for DataProvider feature to `Sample` ([bee6b1f](https://github.com/php-testo/testo/commit/bee6b1f9b44805a65ce5b420e1c8b1941bbc0f64))
* **renderer:** Support data providers in Teamcity renderer ([8794e7e](https://github.com/php-testo/testo/commit/8794e7e4de91ee145ca645572a7db4e42c8fa6ae))
* **renderer:** Support data providers in Terminal renderer ([cea17b2](https://github.com/php-testo/testo/commit/cea17b2170790f57930fbcad8ab2fba4d697ab63))
* streamline failure handling and remove deprecated interceptors ([cd32d42](https://github.com/php-testo/testo/commit/cd32d420f372d3fb9ac67e1561b5ea8668fa77d6))

## [0.2.0](https://github.com/php-testo/testo/compare/0.1.0...0.2.0) (2025-10-29)


### Features

* Add `ConfigInflector` with attributes ([e8cad3e](https://github.com/php-testo/testo/commit/e8cad3e1a011ba7d2d2b5a59c671e1d34bc3c38c))
* Add `Inflector` support in Container ([585213a](https://github.com/php-testo/testo/commit/585213afa285ad02b4da7fdbc8a4f5f6fca714b4))
* **assert:** add `Assert:equal()`, `Assert::notEqual()` ([eb3da87](https://github.com/php-testo/testo/commit/eb3da877e1b225cee86369f19098f235b4ee0f1b))
* Process `--config` flag; ([e38f5a5](https://github.com/php-testo/testo/commit/e38f5a5ae73e41a0bd8286f668b037d5c24724c3))


### Documentation

* **README:** Add configuration example ([82e7881](https://github.com/php-testo/testo/commit/82e788145c56e07ee861fdd93ddcab2ad5f2e189))
* **README:** Add section on running tests and writing test examples ([97643f0](https://github.com/php-testo/testo/commit/97643f0b3adf5392e419bfa3c3f5336f97cbb2d1))
* **README:** Enhance introduction with description of Testo framework ([73ac506](https://github.com/php-testo/testo/commit/73ac506e1cf4982d72e13d401e4fa563ba919e41))
* Update README ([c42b58e](https://github.com/php-testo/testo/commit/c42b58e73f7b81c9bb85f48739ea1615fef8dd70))
* Update README with new logo and support links ([1adf91c](https://github.com/php-testo/testo/commit/1adf91cc545a9c2a79fa68c59630cbc3f6839782))


### Code Refactoring

* **Assert:** rename `equal` to `equals` ([1bdbca2](https://github.com/php-testo/testo/commit/1bdbca2ddac6f10bd57480e83b89cbe58e6b8291))
* Merge Application and Bootstrap; ([247fa05](https://github.com/php-testo/testo/commit/247fa057a64ff0c8770248d77b654bba568e2a24))
