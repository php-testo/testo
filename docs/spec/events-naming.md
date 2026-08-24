# Event Naming Convention

## Overview

This document defines naming conventions for events in the Testo framework, ensuring consistency and clarity across the event system.

## Core Naming Pattern

Events follow the pattern: **`{Entity}{Action}`**

- **Entity**: The subject of the event (e.g., `Test`, `TestPipeline`, `TestBatch`)
- **Action**: The state or action using present participle (`-ing`) or past participle form

## Lifecycle Events

Use **symmetric pairs** for lifecycle boundaries:

- **`{Entity}Starting`** / **`{Entity}Finished`** - for processes with clear start/end

### Examples

```php
TestPipelineStarting / TestPipelineFinished
TestBatchStarting / TestBatchFinished
TestStarting / TestFinished
```

## Single-Point Events

For events that represent a single moment in time (not a lifecycle boundary), use present participle (`-ing`) form:

- **`{Entity}{Action}ing`** - indicates an action happening at a specific moment

### Examples

```php
TestRetrying  // Event fired when a test is about to be retried
```

## Naming Hierarchy

When events form a hierarchy, the entity name should reflect the nesting level:

```
Session         → Outermost level (entire test run lifecycle)
  Worker        → Subprocess level (parallel/isolated execution)
    TestSuite   → Suite level (configured collection of test cases)
      TestCase  → File-scope level (one class, or the functions of one file)
        TestPipeline  → Pipeline level (run interceptors)
          TestBatch   → Batch level (DataProvider/Retry)
            Test      → Individual test execution
```

### Complete Event Hierarchy Example

```php
SessionStarting        // Before any tests are discovered or executed
  WorkerStarting       // Before a subprocess starts (parallel/isolated mode)
    TestSuiteStarting  // Before a test suite (collection of cases) starts
      TestCaseStarting // Before a test case (file-scope group of tests) starts
        TestPipelineStarting   // Before run interceptors start
          TestBatchStarting    // Before batch of test runs (DataProvider/Retry)
            TestStarting       // Before individual test execution
            TestFinished       // After individual test execution
            TestRetrying       // Before retry attempt (if applicable)
            TestStarting       // Next attempt
            TestFinished
          TestBatchFinished    // After all test runs in batch
        TestPipelineFinished   // After run interceptors finish
      TestCaseFinished // After a test case finishes
    TestSuiteFinished  // After a test suite finishes
  WorkerFinished       // After a subprocess finishes
SessionFinished        // After all tests complete, carries RunResult
```

## Event Class Structure

### Basic Event

```php
/**
 * Event triggered [when/before/after] {description}.
 *
 * {Additional context about when and why this event fires}
 *
 * @psalm-immutable
 */
final class {EventName} extends TestEvent {}
```

### Event with Payload

```php
/**
 * Event triggered when {description}.
 *
 * @psalm-immutable
 */
final class TestRetrying extends TestEvent
{
    public function __construct(
        public readonly int $attempt,
    ) {}
}
```

## Documentation Requirements

Each event class must have a PHPDoc comment that includes:

1. **Summary**: One-line description of when the event is triggered
2. **Context**: Additional details about the event's purpose and usage
3. **`@psalm-immutable`**: Mark all events as immutable

### Documentation Template

```php
/**
 * Event triggered [timing] [description].
 *
 * [Additional context explaining:]
 * - When exactly this event fires
 * - What it signals to listeners/renderers
 * - How it relates to other events
 *
 * @psalm-immutable
 */
```

## Naming Anti-Patterns

### ❌ Avoid

- **`Before{Entity}`** / **`After{Entity}`** - Less natural, breaks autocomplete grouping
  ```php
  BeforeTest / AfterTest  // ❌ Don't use
  ```

- **`{Entity}Before{Action}`** / **`{Entity}After{Action}`** - Too verbose
  ```php
  TestBeforeRun / TestAfterRun  // ❌ Too verbose
  ```

- **`{Entity}Start`** / **`{Entity}End`** - Inconsistent with `-ing` pattern
  ```php
  TestStart / TestEnd  // ❌ Less natural
  ```

- **`{Entity}Retried`** - Past tense for single-point events
  ```php
  TestRetried  // ❌ Should be TestRetrying
  ```

### ✅ Prefer

- **`{Entity}Starting`** / **`{Entity}Finished`** - Clear, symmetric, natural
  ```php
  TestStarting / TestFinished  // ✅ Correct
  ```

- **`{Entity}{Action}ing`** - For single-point events
  ```php
  TestRetrying  // ✅ Correct
  ```

## Benefits of This Convention

1. **Autocomplete-friendly**: Typing `Test` shows all test-related events grouped together
2. **Symmetric naming**: `Starting`/`Finished` pairs are clear and consistent
3. **Natural language**: Events read naturally in code (`new TestStarting()`)
4. **Hierarchy clarity**: Entity names reflect nesting levels
5. **Ecosystem alignment**: Consistent with Laravel, Symfony event naming patterns

## Namespace Organization

Events are grouped into namespaces by entity layer. The namespace name matches the entity
prefix for test-domain events. Framework-level events are the exception — `Session` and
`Worker` share a single `Framework` namespace since they belong to the same infrastructure
layer and are not test-domain entities.

```
Event\Framework\     → Session*, Worker*  (infrastructure lifecycle)
Event\TestSuite\     → TestSuite*
Event\TestCase\      → TestCase*
Event\Test\          → TestPipeline*, TestBatch*, TestDataSet*, Test*
Event\Message\       → Message*  (messages recorded during a run; cross-cutting, not a hierarchy level)
Event\Report\        → Report*   (artifacts a reporter writes; cross-cutting, not a hierarchy level)
```

## Examples from Testo

### Framework Events (`Event\Framework`)

Top-level lifecycle events not tied to any specific test entity. Grouped in their own
namespace since `Session` and `Worker` belong to the same infrastructure layer.

```php
SessionStarting        // Before the entire test run begins (fired once)
SessionFinished        // After all tests complete, carries RunResult with duration
WorkerStarting         // Before a subprocess starts (parallel/isolated mode)
WorkerFinished         // After a subprocess finishes
```

### Suite and Case Events
```php
TestSuiteStarting      // Before a suite (configured collection of test cases) starts
TestSuiteFinished      // After a suite finishes
TestCaseStarting       // Before a test case (one class, or the functions of one file) starts
TestCaseFinished       // After a test case finishes
```

### Pipeline Events
```php
TestPipelineStarting   // Before run interceptors
TestPipelineFinished   // After run interceptors
```

### Batch Events
```php
TestBatchStarting      // Before multiple test runs (DataProvider/Retry)
TestBatchFinished      // After all runs complete
```

### Individual Test Events
```php
TestStarting           // Before each test execution
TestFinished           // After each test execution
TestRetrying           // When retry is triggered
```

### Report Events (`Event\Report`)

A pair around one write, both extending `ReportEvent` and carrying the same payload. The verb stays the
one the reader knows — a report is *generated*, not started and finished — so the pair is the present and
past participle of it rather than `ReportStarting`/`ReportFinished`.

```php
ReportFileGenerating   // Before the first byte; the path is where the file will be
ReportFileGenerated    // After the file is written and closed
```

The name states the kind of report as well as the moment, so a consumer subscribes to the kind it can act
on — a file it opens, a URL it would follow — and a kind that is not a file arrives as its own pair beside
this one. The payload is the same card for all of them: `Testo\Core\Report\ReportInfo`, read off the event
as `$event->info`, holding the format, the label and a `Stringable` location — a `Path` for a file, a URL
for a report published to a service.

Any reporter dispatches them — the HTML report, JUnit XML, the `--log-json` file, each coverage report —
and the renderer that owns stdout decides how to state them. A reporter that printed its own path
instead would have to know which renderer is active.
