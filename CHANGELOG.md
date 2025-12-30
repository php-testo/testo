# Changelog

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
