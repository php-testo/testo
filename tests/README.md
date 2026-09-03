# Testing Testo

Testo is a modular framework.
Each module will eventually become a separate Composer package. Until that happens,
the test folder structure should be maintained in a way that allows for easy migration
of tests to separate module repositories in the future.

For example, the `tests/Assert` folder contains tests for the Assert module.
The `tests/Assert/suites.php` file lists the test suite configurations for this module.
Currently, these configurations are merged into the main testing config `testo.php`,
integrating the Assert module tests into the overall Testo testing process.

Tests must be isolated from other tests, meaning each module should have
its own fixtures, mock objects, etc.

Pass `--log-html` (or `--log-report`) to write an HTML report of the run — open it in a browser to inspect
what ran, with channel output, failures and timings. Without the flag no report is written: the default
`HtmlPlugin` is inert. The flag with no path uses `runtime/report/index.html`, overwritten by the next run.

## Self Tests

In addition to the commonly known types of tests (unit tests, integration tests, etc.),
Testo has another type of test - Self Tests.

Example of a test that tests itself:

```php
#[Test]
public function numbers(): void
{
    Assert::equals(1, 1);
    Assert::equals(1, 1.0);
    Assert::equals(1.0, 1);
    Assert::equals("2", 2);
}
```

If the test completes with Passed status, we have verified that:
- `Assert::equals` registers a successful assertion in the Test State, otherwise the test would be marked as Risky.
- `Assert::equals` doesn't fail on the provided values.

Special attributes will soon be added for Self Tests to simplify their creation,
for example `#[Testing\ExpectStatus(Status::Failed)]` and others.
