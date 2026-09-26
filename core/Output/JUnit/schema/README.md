# JUnit XML schema, version 1

XML Schemas for the JUnit report Testo writes. `https://php-testo.github.io/schema/junit/1` is the namespace of the attributes Testo adds to the report; it is an identifier, not a URL to fetch.

- [`junit.xsd`](junit.xsd) describes a JUnit XML report as a whole. JUnit XML never had an official schema, so every tool ships its own dialect; this schema is their union and accepts reports from Apache Ant, Maven Surefire, the JUnit 5 Platform, PHPUnit, Pest, Codeception, pytest, jest-junit, go-junit-report and Testo.
- [`testo.xsd`](testo.xsd) declares the `testo:*` attributes. `junit.xsd` imports it, so validating against `junit.xsd` checks them as well.

## Validating a report

Point any XSD 1.0 validator at `junit.xsd`, keeping `testo.xsd` next to it:

```php
$report = new DOMDocument();
$report->load('runtime/junit.xml');
$report->schemaValidate('vendor/testo/testo/core/Output/JUnit/schema/junit.xsd');
```

The same works with `xmllint --noout --schema junit.xsd runtime/junit.xml`.

## How the superset is built

The base is the Maven Surefire dialect, the one Jenkins' xunit plugin publishes as `junit-10.xsd`. It is the most permissive of the published schemas: suites nest, the children of a `<testcase>` come in any order, and the rerun elements Surefire and Testo use for retried tests are part of it. The stricter Ant-derived schema that Azure DevOps links to requires `<properties>`, `<system-out>` and `<system-err>` in every suite, which almost no producer writes.

On top of that base, every attribute and element another producer writes is added as optional: `assertions` and `file` from PHPUnit, `class` from PHPUnit 10+, `useless` from Codeception, `url` from pytest, `disabled` and `status` from Jenkins' documented format, `line` on suites from GitHub's action-junit-report. Counters must be non-negative integers, durations must be numbers (Surefire's `1,234.5` grouping included), timestamps must be ISO 8601.

Any element accepts attributes from a foreign namespace, which covers `xsi:*` hints and vendor attributes like Testo's, and suites and test cases accept child elements from a foreign namespace. Unknown unqualified markup is rejected: a typo like `<testcsae>` or `fialures="1"` fails validation.

## Testo attributes

All of them live on `<testcase>`. A consumer that doesn't know the namespace skips them.

| Attribute | Value | Written |
|---|---|---|
| `testo:status` | `passed`, `failed`, `skipped`, `error`, `risky`, `flaky`, `cancelled` or `aborted` | on every test case |
| `testo:data-provider` | zero-based index of the data provider | on data-set rows |
| `testo:data-set` | zero-based position of the data set within its provider | on data-set rows |
| `testo:data-set-key` | the key the provider yielded the data set under | on data-set rows with a key |

`testo:status` is there because the standard elements fold eight statuses into four outcomes: risky and aborted tests are written as `<error>`, cancelled ones as `<skipped>`, and a flaky test passes. The data-set pair addresses a single row on the command line: `--filter='App\UserTest::createsUser:0:2'`.

## Versioning

The `1` in the namespace is the major version. A change that only makes the schema accept more (a new optional attribute, a new element, a new `testo:status` value) is made in place. A change that would reject a report version 1 accepts gets a new namespace, `…/junit/2`.
