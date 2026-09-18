# Where a test's own classes live

Reference for `testo-test-doubles`. Every **named** class a test needs — a double, a fixture, a
test-specific subclass — is a file of its own under the suite. A class trailing at the foot of the
test file that first wanted it is invisible to PSR-4: it loads only because Testo included that test
file, so the second test to reference it fails with class-not-found, and static analysis never sees it.

A stand-in needed by exactly one test needs no name and no file — write it as an anonymous class in
the test body. The moment it earns a name, it earns a file.

## Kind → directory

Two homes, split by what the class does for the test:

| The class… | Directory | Naming |
|---|---|---|
| **stands in** for a collaborator — fake, stub, spy, dummy, throwing | `tests/<Suite>/Stub/` | prefix states the kind (`SpyMailer`, `InMemoryUserRepository`) — table in `handwritten.md` |
| **is material** the SUT runs against — synthetic value object, data-provider target, an interface declared to be doubled, a subclass of the SUT that exists to observe it | `tests/<Suite>/Fixture/` | names the role, no prefix (`BackupFile`, `Greeter`, `CombinatorTarget`) |

The test: a stand-in *answers instead of* something real, so the test could have used the real thing;
material has no real counterpart — the test invented it to have something to feed in or watch.

Testo's own suites are laid out this way, both directories side by side under one suite:

```
bridge/double/tests/Stub/DoubleScenarios.php          stand-in
bridge/double/tests/Fixture/Greeter.php               material: a class built to be doubled
bridge/double/tests/Fixture/Permissions.php           material: an interface built to be doubled
plugin/data/tests/Unit/Fixture/CombinatorTarget.php   material: a target the providers drive
tests/Application/Stub/SpyDispatcher.php              stand-in
```

A **test-specific subclass** — one that extends the SUT purely so the test can observe it, e.g. assert
that a method returning `new static` preserves the late-bound class — is material, not a double. It
adds no behaviour and replaces none; it exists to be recognised. `Fixture/` is its home.

## Rules for either directory

- **Namespace mirrors the path**: `tests/Unit/Fixture/BackupFile.php` → `Tests\Unit\Fixture\BackupFile`.
- **`autoload-dev` maps the `Tests\` prefix onto `tests/`.** Without it the class is not autoloadable
  and the test fails with class-not-found.
- **One class per file**, and one collaborator per double. Two interfaces mean two classes, even when
  the tests always use them together.
- **Test attributes belong to the test class.** Discovery is attribute-based, so a double or fixture
  under a suite's `location` is never collected as long as it carries no `#[Test]`. The one class that
  keeps its `#[Test]` methods is a *scenario*: a test class the real test runs itself through
  `Testo\Testing\Helper\TestRunner` to observe the engine. It lives in `Stub/` or `Fixture/` like any
  other, and the suite lists that directory in `FinderConfig(exclude:)` so discovery never collects it.
- **The suite that uses the class owns it**: `tests/Billing/Fixture/` by default. A repository-wide
  `tests/Fixture/` earns its place only for material every suite genuinely shares — a data provider,
  a `functions.php` of free functions. A fixture pulled in by three suites for three reasons has a
  shape that belongs to none of them.
