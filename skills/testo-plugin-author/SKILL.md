---
name: testo-plugin-author
description: Author a Testo plugin — event listeners, interceptors, custom container bindings, or new test attributes. Use when the user wants to extend Testo's behaviour (custom reporters, lifecycle hooks across the suite, attribute-driven middleware, integrating an external system) rather than writing a single test.
---

# Authoring a Testo plugin

Testo's extensibility is the reason to choose it. Plugins implement `PluginConfigurator` and wire
themselves into the per-suite DI container. From there a plugin can:

- Subscribe to lifecycle **events** (PSR-14 dispatcher) — `TestStarting`, `TestFinished`, `TestSuiteStarting`, …
- Register **interceptors** (middleware) that wrap test execution.
- Bind services into the container — replace or augment Testo's defaults.
- Discover and act on **custom attributes** placed on test classes/methods.

**This is the advanced surface** — escalate to `https://php-testo.github.io/llms-full.txt` before
writing real code. The concise `llms.txt` covers test authoring; the full doc has the plugin
architecture, the container/interceptor APIs, and the event class hierarchy.

## Concepts you must get right

- **Test** — a single test method (`#[Test]` method, function, or `#[TestInline]` case).
- **Test Case** — file-scope group of tests: methods of one class, or functions of one file. A file with several test classes yields several Test Cases.
- **Test Suite** — a named, configured collection of Test Cases (`SuiteConfig`). **Suite is the smallest unit a plugin can be applied to** — different suites can have different plugin sets.

Event hierarchy fires top-down: `Session` → `Worker` → `TestSuite` → `TestCase` → `TestPipeline` → `TestBatch` → `Test`. A listener on `TestCaseFinished` fires once per case (all its tests done), not per test method.

## When a plugin is the right answer

Reach for a plugin when:

- You need behaviour for **every test** in a suite (logging, telemetry, DB transaction wrapping).
- You want a custom **attribute** that changes how a test runs (e.g. `#[OnlyOnCI]`).
- You're integrating with an external system (a custom reporter, an APM, a coverage backend).

Don't reach for a plugin when a single `#[BeforeTest]` hook in one class would do.

## Minimal event-listener plugin

```php
<?php
declare(strict_types=1);

namespace App\Testing\Plugin;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\Test\TestFinished;

final class FailureLoggerPlugin implements PluginConfigurator
{
    public function __construct(
        private readonly string $logFile = 'failures.log',
    ) {}

    public function configure(Container $container): void
    {
        $container
            ->get(EventListenerCollector::class)
            ->addListener(TestFinished::class, $this->onTestFinished(...));
    }

    private function onTestFinished(TestFinished $event): void
    {
        if (!$event->testResult->status->isFailure()) {
            return;
        }

        \file_put_contents(
            $this->logFile,
            \sprintf("[%s] %s\n", \date('c'), $event->testInfo->testDefinition->reflection->getName()),
            FILE_APPEND,
        );
    }
}
```

Register it in `testo.php`:

```php
new SuiteConfig(
    name: 'Unit',
    location: ['tests/Unit'],
    plugins: [new FailureLoggerPlugin(logFile: __DIR__ . '/runtime/failures.log')],
),
```

## Plugin scope: application vs. suite

- `ApplicationConfig::$plugins` — applied to **every suite**. Use for cross-cutting concerns (coverage, JUnit reporting).
- `SuiteConfig::$plugins` — applied only to that suite. Use for suite-specific behaviour (inline tests in `Sources` only, slow-test markers in `Integration` only).
- `SuitePlugins::only(...)` — replaces the inherited application plugins entirely for that suite. Use sparingly, and surface to the user when you do.

## Custom attribute → interceptor

For attributes that change *how* a test runs (skip, wrap in a transaction, set a fixture), register an
**interceptor** via the `InterceptorCollector`. Read `llms-full.txt` for the interceptor signature and
chaining rules — they are version-specific and easy to get wrong from memory.

Outline:

1. Define the attribute as a final readonly class with `#[\Attribute(...)]`.
2. In `configure(Container $container)`, get the `InterceptorCollector` and add an interceptor that:
   - Inspects the test reflection for your attribute.
   - Wraps the next callable with whatever behaviour you need (skip, retry, DB tx).
3. Plugin sits in `ApplicationConfig::$plugins` so the attribute works everywhere.

## Replacing a default service

The container can rebind interfaces — e.g. swap the default JUnit writer for a custom one. Pattern:

```php
public function configure(Container $container): void
{
    $container->bind(JUnitWriter::class, MyTeamCityWriter::class);
}
```

Verify the interface name and namespace against `llms-full.txt` for the version in use before binding.

## Pitfalls

- **Don't import from `Internal\` namespaces lightly.** Testo treats `Internal\*` as not-public; future versions may move things. Prefer types under `Testo\*`.
- **Don't capture `$container` in your listener closure.** Resolve services in `configure()` and inject them into the listener (closure-bound or via a constructor arg) — keeps the listener cheap and testable.
- **Don't mutate event objects.** Listeners are observers; if you need to change test behaviour, write an interceptor, not a listener.
- **Don't write a plugin to fix one test.** Solve the per-class case with a `#[BeforeTest]` first.
- **Test your plugin.** Self-tests live in `plugin/<name>/tests/` in the Testo repo; mirror that layout in user repos so a plugin can later be extracted to a package.
