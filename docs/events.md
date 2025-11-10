# Events

Testo emits events throughout the test execution lifecycle. Events are your primary extension point for customizing test behavior, collecting metrics, or integrating with external tools.

## Event System Basics

Events in Testo are classes, following [PSR-14](https://www.php-fig.org/psr/psr-14/) event dispatcher standard.

Event characteristics:
- **Immutable** - you cannot modify event objects. Listen and react, don't mutate.
- **Type-safe** - every event is a concrete class with typed properties.
- **Hierarchical** - events are organized by scope: suite → case → test.
- **Polymorphic** - you can listen to parent classes or interfaces to catch multiple event types.

## Registering Event Listeners

Register listeners via `EventListenerCollector` interface in your plugins. See [plugins.md](plugins.md) for plugin development.

```php
use Testo\Config\EventListenerCollector;
use Testo\Test\Event\Test\TestFinished;

class MyPlugin
{
    public function configure(EventListenerCollector $events): void
    {
        $events->addListener(
            TestFinished::class,
            fn(TestFinished $event) => $this->onTestFinished($event),
            priority: 100
        );
    }

    private function onTestFinished(TestFinished $event): void
    {
        // React to test completion
        $testInfo = $event->testInfo;
        $result = $event->testResult;
    }
}
```

**Priority:** Higher values execute first. Default is `0`.

## Event Hierarchy

Events fire at three levels of granularity:

### Suite Level

One test class = one suite. Contains multiple test cases (methods).

```
TestSuitePipelineStarting      # Before suite interceptors
  TestSuiteStarting            # Suite execution begins
    ... test cases run ...
  TestSuiteFinished            # Suite execution ends
TestSuitePipelineFinished      # After suite interceptors
```

### Case Level

One test method = one case. May contain multiple test runs (via data providers or retries).

```
TestCasePipelineStarting       # Before case interceptors
  TestCaseStarting             # Case execution begins
    ... test batches run ...
  TestCaseFinished             # Case execution ends
TestCasePipelineFinished       # After case interceptors
```

### Test Level

One execution of a test = one test run. This is the most granular level.

```
TestPipelineStarting           # Before test interceptors
  TestBatchStarting            # Batch begins (for data providers/retries)
    TestStarting               # Single test run starts
    TestFinished               # Single test run ends
    TestRetrying               # (optional) Test will be retried
    TestStarting               # Retry attempt starts
    TestFinished               # Retry attempt ends
  TestBatchFinished            # Batch ends
TestPipelineFinished           # After test interceptors
```

## Event Ordering Rules

1. **Pipeline events** always wrap execution:
   - `*PipelineStarting` fires first
   - `*PipelineFinished` fires last

2. **Starting/Finished pairs** always match:
   - Every `*Starting` has a corresponding `*Finished`
   - Even if execution fails or is skipped

3. **Hierarchy flows downward:**
   ```
   Suite Pipeline Start
     Suite Start
       Case Pipeline Start
         Case Start
           Test Pipeline Start
             Batch Start
               Test Start
               Test Finish
             Batch Finish
           Test Pipeline Finish
         Case Finish
       Case Pipeline Finish
     Suite Finish
   Suite Pipeline Finish
   ```

4. **Batches group runs:**
   - `TestBatchStarting` fires once per test method
   - Contains multiple `TestStarting`/`TestFinished` pairs
   - Ends with `TestBatchFinished` containing aggregated result

5. **Retries fire between runs:**
   - `TestFinished` (failed)
   - `TestRetrying` (decision to retry)
   - `TestStarting` (retry attempt begins)

## Polymorphic Listeners

Since events are classes, you can listen to parent classes or interfaces to handle multiple event types in one listener.

### Listen to All Test Events

```php
use Testo\Test\Event\Test\TestEvent;

$events->addListener(TestEvent::class, function (TestEvent $event) {
    // Fires for: TestStarting, TestFinished, TestRetrying, TestBatchStarting, etc.
    $this->logger->debug("Test event: " . get_class($event));
});
```

### Listen to All Events with Results

```php
use Testo\Test\Event\Test\TestResultEvent;

$events->addListener(TestResultEvent::class, function (TestResultEvent $event) {
    // Fires for: TestFinished, TestBatchFinished, TestPipelineFinished
    if ($event->testResult->isFailed()) {
        $this->notifyFailure($event);
    }
});
```

### Listen to All Case Events

```php
use Testo\Test\Event\TestCase\TestCaseEvent;

$events->addListener(TestCaseEvent::class, function (TestCaseEvent $event) {
    // Fires for all TestCase* events
    $this->trackCase($event->caseInfo);
});
```

This is useful when you don't care about the specific event type, only the data it carries.

## Common Use Cases

### Collecting Test Metrics

```php
$events->addListener(TestFinished::class, function (TestFinished $event) {
    $duration = $event->testResult->executionTime;
    $memory = $event->testResult->memoryUsage;

    $this->metrics->record($event->testInfo->name, $duration, $memory);
});
```

### Custom Test Output

```php
$events->addListener(TestCaseStarting::class, function (TestCaseStarting $event) {
    echo "Running: {$event->caseInfo->className}::{$event->caseInfo->methodName}\n";
});
```

### Retry Notifications

```php
$events->addListener(TestRetrying::class, function (TestRetrying $event) {
    $this->logger->warning(
        "Retrying test {$event->testInfo->name}, attempt {$event->attempt}"
    );
});
```

### Integration with External Tools

```php
$events->addListener(TestSuiteFinished::class, function (TestSuiteFinished $event) {
    $this->externalReporter->sendSuiteResults(
        $event->suiteInfo,
        $event->suiteResult
    );
});
```

## Custom Event Dispatcher

Testo uses PSR-14 compliant event dispatcher. You can replace it via the [plugin system](plugins.md) by providing your own `EventDispatcherInterface` and `EventListenerCollector` implementations (PSR-14 doesn't define listener configuration, so Testo uses `EventListenerCollector` as the API for this).

**Warning:** While Testo's core doesn't depend on events, many components do. Using a `NullDispatcher` or non-functional dispatcher will break:
- Built-in renderers (progress reporting, output formatting)
- Plugins that rely on events
- Integration with external tools

Only replace the dispatcher if you understand the implications.

## Important Notes

- **Events are read-only.** You cannot change test outcomes by modifying events.
- **Listeners execute synchronously.** Heavy processing will slow down test execution.
- **Exceptions in listeners will halt execution.** Handle errors gracefully.
- **Priority matters.** If listener order is important, use explicit priorities.
- **Pipeline events** exist to separate interceptor boundaries from logical test boundaries.
- **Polymorphic listeners** let you catch multiple event types with one handler by listening to parent classes.
