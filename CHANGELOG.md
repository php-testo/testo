# Changelog

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
