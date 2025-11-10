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
TestPipeline    → Top level (run interceptors pipeline)
  TestBatch     → Mid level (multiple runs of same test)
    Test        → Individual test execution
```

### Complete Event Hierarchy Example

```php
TestPipelineStarting   // Before run interceptors start
  TestBatchStarting    // Before batch of test runs (DataProvider/Retry)
    TestStarting       // Before individual test execution
    TestFinished       // After individual test execution
    TestRetrying       // Before retry attempt (if applicable)
    TestStarting       // Next attempt
    TestFinished
  TestBatchFinished    // After all test runs in batch
TestPipelineFinished   // After run interceptors finish
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

## Examples from Testo

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
