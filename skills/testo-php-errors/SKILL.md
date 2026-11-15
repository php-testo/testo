---
name: testo-php-errors
description: 'Surface PHP warnings, notices and deprecations raised during Testo tests with the testo/error-handler plugin: collect them per test, print them to the stderr channel, or fail the test on them. Use when a warning or deprecation scrolls by while the test passes, when deprecations must fail the build, when tested code calls set_error_handler() itself, or when a run is marked Risky with "did not remove its own error handlers".'
---

# PHP errors in Testo tests

Testo core leaves PHP errors to PHP: a warning raised inside a test is printed by the engine and
the test still passes. The `testo/error-handler` plugin puts an error handler around every test,
records what it sees on the `TestResult`, and can turn an error into a failure.

Fetch `https://php-testo.github.io/llms.txt` before editing tests or `testo.php`.

## Enable

```bash
composer require --dev testo/error-handler
```

```php
// testo.php
use Testo\ErrorHandler\ErrorHandlerPlugin;

plugins: [
    new ErrorHandlerPlugin(),                  // collect only (default)
    // new ErrorHandlerPlugin(failOnError: true), // any captured error fails the test
],
```

Application-wide `plugins:` covers every suite; a suite's own `plugins:` scopes it to that suite.

## What happens to an error

| The error… | Captured on the result | Written to `stderr` channel | `failOnError: true` |
|---|---|---|---|
| is raised with nobody else handling it | yes | yes, as PHP would print it | fails |
| is taken by a handler installed before the test (it returned `true`) | yes, `handled = true` | no | fails |
| is turned into an exception by that handler | no | no | the exception is the test's own control flow |
| is silenced with `@` or excluded by `error_reporting()` | no | no | passes |
| is `E_USER_ERROR` | no | no | thrown as `\ErrorException` in every mode |

The handler that was installed before the test (an application bootstrap, a framework) runs first
for every error, sees the real `error_reporting()` level, and keeps its return value semantics.
PHP's own printing is suppressed: the `stderr` channel replaces it, so nothing appears twice.

Captured errors live in the result attribute `Testo\ErrorHandler\CapturedErrors::class`, a list of
`CapturedError` (`severity`, `message`, `file`, `line`, `handled`; `(string) $error` gives the PHP
wording). The `stderr` channel shows in the terminal under a `[stderr]` header and reaches every
reporter that renders messages.

`failOnError: true` upgrades only a **passing** test: the first captured error becomes an
`\ErrorException` failure. A test that already failed or errored keeps its own failure.

## Tested code that installs an error handler

The error-handler stack is process-global. After every test the plugin puts it back exactly as it
found it, then judges what the test did to it:

| After the test… | Outcome |
|---|---|
| the stack is as it was | as the test finished |
| a handler the test installed is still on the stack, or the plugin's own handler is gone | passing test is `Status::Risky`, reason in the `error-handler` message channel |

A test whose job is to install a handler and leave it (a bootstrap, a `register()` method)
declares that with `#[ExpectErrorHandlerChange]`:

```php
use Testo\ErrorHandler\ExpectErrorHandlerChange;

#[Test]
#[ExpectErrorHandlerChange]
public function registersTheApplicationHandler(): void
{
    (new ErrorBootstrap())->register();

    Assert::true(ErrorBootstrap::isRegistered());
}
```

Allowed on a method, a function, or a class (then it covers every test of the class). The
declaration is a two-way contract: a marked test that leaves the stack unchanged is
`Status::Failed` with `Testo\ErrorHandler\Exception\ErrorHandlerUnchanged`.

A test that installs a handler and removes it again before returning needs no attribute.

## Fibers

The plugin's handler and anything the test installed above it leave the stack whenever the test
fiber suspends and come back when it resumes. A test under `#[RunInFiber]` or `#[RunInRevolt]`
sees its own handler after every `await`, and a sibling running in the gap never has it.

## Pitfalls

- Without the plugin a warning is not a failure and appears in no report. Add the plugin before
  writing a test that expects a warning to matter.
- Turning a warning into an exception is the previous handler's job, not the plugin's. Check
  `CapturedErrors` or use `failOnError`; do not `Expect::exception(\ErrorException::class)` unless a
  bootstrap handler throws it.
- `trigger_error(..., E_USER_ERROR)` is deprecated since PHP 8.4 and always throws here; do not use
  it to "fail from inside" a test, throw or `Assert::fail()` instead.
- `#[ExpectErrorHandlerChange]` is for tests *about* error handlers. A test that merely triggers
  errors never needs it.
