---
name: testo-inline-tests
description: 'Attach #[TestInline] cases directly to production methods so the example/doctest lives next to the implementation. Use when the user asks for "inline tests", "doctests", "examples next to code", or wants quick example-based verification of a pure function without a dedicated test class.'
---

# Inline tests in Testo (`#[TestInline]`)

`#[TestInline]` lets you attach example inputs and an expected return value directly to a method
in production code. The Testo runner discovers and executes them like normal tests, but they live
next to the implementation — handy for pure functions and library primitives.

This requires the `InlineTestPlugin` to be enabled for the suite that scans the relevant directory.
Fetch `https://php-testo.github.io/llms.txt` for the attribute signature and `llms-full.txt` for the
plugin wiring details — verify them before generating code, the API surface is small but specific.

## Canonical shape

```php
<?php
declare(strict_types=1);

namespace App\Math;

use Testo\Inline\TestInline;

final class Vector
{
    #[TestInline([1, 2],  3)]
    #[TestInline([5, 10], 15)]
    #[TestInline([-1, 1], 0)]
    public static function sum(int $a, int $b): int
    {
        return $a + $b;
    }
}
```

- Each `#[TestInline]` attribute = one case.
- First argument: array of method arguments (positional).
- Second argument: expected return value (compared with strict equality).
- The decorated method **must be deterministic and side-effect-free** — inline tests aren't for I/O or stateful code.

## When inline tests are the right choice

Use `#[TestInline]` when **all** of the following hold:

- The method is pure (same inputs → same output, no side effects).
- The examples document behaviour that readers of the source will benefit from seeing.
- Each example fits comfortably on one line.

Otherwise prefer a regular `#[Test]` class — `testo-write-tests` skill.

## When inline tests are wrong

- The method has dependencies that need wiring (use DI + a normal test).
- The expected value isn't a simple literal (no fluent chain, no objects with identity).
- The test needs setup/teardown — `#[TestInline]` cannot use `#[BeforeTest]` hooks.
- You want to assert exceptions — use `Expect::exception(...)` inside a regular test.

## Configuration check

For inline tests to run, the suite scanning the *production* directory must include `InlineTestPlugin`.
Excerpt from `testo.php`:

```php
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Inline\InlineTestPlugin;

new SuiteConfig(
    name: 'Sources',
    location: ['src'],
    plugins: SuitePlugins::only(new InlineTestPlugin()),
),
```

If the user adds `#[TestInline]` but the test never runs, check the suite plugin list first.

## Pitfalls

- Don't put `#[TestInline]` on methods that touch globals, the filesystem, time, or randomness.
- Don't try to label cases with a third argument — case identity is the attribute position. Re-order, don't relabel.
- A failing `#[TestInline]` reports the method's FQN + the attribute index as the test identifier. Order matters for diff readability.
