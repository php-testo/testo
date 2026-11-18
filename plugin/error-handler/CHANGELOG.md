# Changelog

## [0.1.1](https://github.com/php-testo/testo/compare/error-handler-0.1.0...error-handler-0.1.1) (2026-11-18)


### Features

* **error-handler:** add `#[ExpectErrorHandlerChange]` ([4cb0fc4](https://github.com/php-testo/testo/commit/4cb0fc438dd3b27a84e649859906e1f19d4aa01d))
* **error-handler:** add `#[WithoutErrorHandler]` to opt a test out of the plugin ([49aaa3d](https://github.com/php-testo/testo/commit/49aaa3da49675f713d167e90f9bbfaf29a2bdcf7))
* **error-handler:** add the error-handler plugin capturing PHP errors per test ([#73](https://github.com/php-testo/testo/issues/73)) ([f5d0052](https://github.com/php-testo/testo/commit/f5d00520df75aa137ac5a17105861acc9c31aad5))
* **error-handler:** forward errors to the previous handler and police the handler stack per test ([4cb0fc4](https://github.com/php-testo/testo/commit/4cb0fc438dd3b27a84e649859906e1f19d4aa01d))
* **error-handler:** register the plugin by default when the package is installed ([50d4ddd](https://github.com/php-testo/testo/commit/50d4ddd50820307573d7d738da0fa537e8017bf0))
* **error-handler:** report unhandled errors through the stderr channel and flag handled ones ([a232878](https://github.com/php-testo/testo/commit/a2328780de4118b116a999fc07f81f2b8402f72e))
