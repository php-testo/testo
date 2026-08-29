---
name: testo-data-driven
description: 'Parameterize Testo tests with #[DataSet], #[DataProvider], #[DataZip], and #[DataCross]. Use when a test should run with multiple inputs, the user asks for "table-driven tests", "parameterized tests", "data providers", or a test method has copy-pasted setup with only the values changing.'
---

# Data-driven tests in Testo

Testo offers five parameterization attributes. Pick by the **shape of the data**, not by habit.

| Attribute | When to use |
|---|---|
| `#[DataSet]` | A handful of fixed cases, written inline next to the test method. One attribute = one case. |
| `#[DataProvider]` | A larger or computed set, produced by a static method (`iterable`). |
| `#[DataUnion]` | Concatenate several providers/datasets into **one logical axis**. Primarily a building block for `DataCross` / `DataZip`. |
| `#[DataZip]` | Pair *N* providers by index (1st with 1st, 2nd with 2nd, …). Arguments from each axis are concatenated per row. |
| `#[DataCross]` | Cartesian product across *N* providers. Each provider contributes its arguments to one slice of the final argument list; combinations multiply. |

Always fetch `https://php-testo.github.io/llms.txt` before writing — the attribute namespaces and
constructor signatures live there and may evolve.

## `#[DataSet]` — inline cases

```php
use Testo\Data\DataSet;
use Testo\Test;
use Testo\Assert;

#[Test]
final class AgeValidatorTest
{
    #[DataSet([12, false], 'below minimum')]
    #[DataSet([18, true],  'within range')]
    #[DataSet([65, false], 'above maximum')]
    public function isValid(int $age, bool $expected): void
    {
        Assert::same(AgeValidator::isValid($age), $expected);
    }
}
```

The second argument is the case label — surface it for every case; it is what shows up in failure output.

## `#[DataProvider]` — method-based cases

```php
use Testo\Data\DataProvider;

#[DataProvider('emailScenarios')]
public function validatesEmail(string $email, bool $valid): void
{
    Assert::same(EmailValidator::check($email), $valid);
}

public static function emailScenarios(): iterable
{
    yield 'valid'   => ['test@example.com', true];
    yield 'invalid' => ['not-email',        false];
    yield 'empty'   => ['',                 false];
}
```

Rules:

- Provider must be `public static` and return `iterable`.
- Prefer `yield 'label' => [...]` over numeric keys — labels appear in output.
- The constructor takes a single `callable|string` argument. A provider on another class is referenced as a callable string: `#[DataProvider(Other::class . '::method')]` (the method must be `public static`).

## `#[DataZip]` — index-aligned pairing

Use when two/more datasets are already aligned positionally and you do not want their cartesian product.

```php
#[DataZip(
    new DataProvider('credentials'),
    new DataProvider('expectedPermissions'),
)]
public function loginYieldsPermissions(string $user, string $pass, array $perms): void
{
    Assert::same((new Auth())->login($user, $pass)->permissions(), $perms);
}
```

If providers have different lengths, the shorter wins — surface that constraint to the user when designing the data.

## `#[DataCross]` — cartesian product

**Mental model.** `DataCross(P1, P2, …, Pn)` takes *n* providers. For every combination of one row from each provider, it **concatenates** their arguments into the final call. So:

- The number of test runs = `|P1| × |P2| × … × |Pn|`.
- The number of method parameters = `arity(P1) + arity(P2) + … + arity(Pn)`.

Each `#[DataSet]` attribute is **one row** of one argument-set. If you want several rows on the same axis, use a `DataProvider`, or wrap several `DataSet`s in `DataUnion`.

### Recommended form: one provider per axis

```php
#[DataCross(
    new DataProvider('browsers'),
    new DataProvider('viewports'),
)]
public function layoutRenders(string $browser, array $size): void
{
    // 2 browsers × 2 viewports = 4 combinations
}

public static function browsers(): iterable
{
    yield 'chrome'  => ['chrome'];
    yield 'firefox' => ['firefox'];
}

public static function viewports(): iterable
{
    yield 'desktop' => [[1920, 1080]];
    yield 'tablet'  => [[768, 1024]];
}
```

### Inline form: `DataUnion` of `DataSet`s per axis

When the data is tiny and you don't want to write provider methods, wrap each axis in a `DataUnion`:

```php
use Testo\Data\{DataCross, DataSet, DataUnion};

#[DataCross(
    new DataUnion(
        new DataSet(['chrome'],  'chrome'),
        new DataSet(['firefox'], 'firefox'),
    ),
    new DataUnion(
        new DataSet([[1920, 1080]], 'desktop'),
        new DataSet([[768, 1024]],  'tablet'),
    ),
)]
public function layoutRenders(string $browser, array $size): void
{
    // 2 × 2 = 4 combinations
}
```

### What does NOT work

```php
// WRONG — 4 axes × 1 row each = 1 run with 4 concatenated arguments,
// but the method takes only 2 parameters → arity mismatch.
#[DataCross(
    new DataSet(['chrome'],  'chrome'),
    new DataSet(['firefox'], 'firefox'),
    new DataSet([[1920, 1080]], 'desktop'),
    new DataSet([[768, 1024]],  'tablet'),
)]
public function layoutRenders(string $browser, array $size): void { /* broken */ }
```

Each argument position of `DataCross` is an **axis**, not a row.

### Mixing multi-arg providers

Each provider can contribute more than one argument. They are concatenated in declaration order:

```php
#[DataCross(
    new DataProvider('numbers'),  // yields [int, int]
    new DataProvider('letters'),  // yields [string, string]
)]
public function combined(int $a, int $b, string $c, string $d): void { }
```

Cardinality explodes quickly — warn the user if any axis exceeds ~5 entries.

## Choosing the right attribute

- One axis, ≤ ~6 cases, all literals → repeated `#[DataSet]`.
- One axis, many cases or computed values → `#[DataProvider]`.
- Two or more axes, **pre-paired** → `#[DataZip]` (each axis = one provider, or a `DataUnion` of `DataSet`s).
- Two or more axes, **independent** → `#[DataCross]` (same rule for axes).
- Need to splice two providers into one axis (e.g. happy-path cases + edge cases) → wrap them in `#[DataUnion]` and pass the union as a single axis to `DataCross` / `DataZip`.

## Boundary coverage

When parameterizing numeric or sized inputs, include: `0`, `1`, `n-1`, `n`, `n+1`, `null`, the type's lower/upper limit.
Surface this checklist when the user asks for "parameterize this test" — don't only port the existing examples.

## Pitfalls

- Don't use `#[DataProvider]` returning `array` of arrays without labels — failure output becomes unreadable.
- **One `#[DataSet]` = one row, not one axis.** Putting several bare `DataSet`s into `DataCross` / `DataZip` adds *axes*, not *rows*. To add rows to one axis, use `DataProvider` or wrap the `DataSet`s in `DataUnion`.
- Verify arity: parameter count of the test method must equal the **sum of arities** of the providers passed to `DataCross` / `DataZip`, not the count of providers.
- Don't nest `#[DataCross]` inside another `#[DataCross]` — flatten by listing all axes once.
- A parameterized method **cannot** also carry inline body assertions that depend on a non-parameterized fixture — move shared setup into a `#[BeforeTest]` hook.
