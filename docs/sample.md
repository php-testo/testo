# Sample Module

The Sample module provides attributes for parameterized testing - running the same test logic with different input data. Think of it as a way to test your functions against multiple scenarios without writing repetitive test code.

Currently includes:
- **DataProvider** - for dynamic, complex data sets
- **TestInline** - for simple, static test cases right on the method

## Data Provider

`DataProvider` lets you specify a method or callable that returns test data. Each data set from the provider runs as a separate test:

```php
use Testo\Attribute\Test;
use Testo\Sample\DataProvider;

#[Test]
#[DataProvider('userDataProvider')]
public function testUserValidation(string $email, bool $expected): void
{
    $isValid = $this->validator->validate($email);
    Assert::same($expected, $isValid);
}

public function userDataProvider(): iterable
{
    yield ['valid@example.com', true];
    yield ['invalid', false];
    yield ['test@domain.co.uk', true];
    // ... 50 more cases
}
```

### Flexible Provider Sources

`DataProvider` accepts various callable types:

**Method name from the same class:**
```php
#[DataProvider('dataProvider')]
public function testSomething($data): void { ... }
```

**Method from another class:**
```php
#[DataProvider([DataSets::class, 'userScenarios'])]
public function testUser($data): void { ... }
```

**Closure directly in attribute (PHP 8.5+):**
```php
#[DataProvider(fn() => [
    [1, 2, 3],
    [5, 5, 10],
])]
public function testAddition(int $a, int $b, int $expected): void { ... }
```

**Invokable object:**
```php
#[DataProvider(new UserDataProvider())]
public function testUser($data): void { ... }
```

Invokable objects are particularly useful for separating data loading logic. For example, loading test cases from JSON/CSV files into a dedicated class keeps your test code clean.

### Labels and Descriptions

Each data set can be labeled with a string key. These labels appear in test reports, making it easier to identify which scenario failed:

```php
public function userDataProvider(): array
{
    return [
        'valid email' => ['test@example.com', true],
        'invalid format' => ['not-an-email', false],
        'empty string' => ['', false],
    ];
}
```

Use `DataProvider` when:
- You have many test cases (10+)
- Data is generated dynamically or loaded from external files
- Test cases need labels or descriptions for clarity
- You need complex setup logic for test data

**Note:** `DataProvider` is an addition to regular tests (methods marked with `#[Test]`). It provides data to existing test methods.

## Inline Tests

`TestInline` takes a different approach - it declares test cases as attributes directly on the method being tested, without requiring a separate test class.

This might be useful for simple pure functions where a dedicated test file would be excessive. It also works well for testing private helper methods - you can test them directly without changing visibility. When prototyping, `TestInline` gives you immediate validation without switching context to a test file.

### The Basics

The attribute signature:
```php
TestInline(array $arguments, mixed $result = null)
```

Declare test cases right on the method:

```php
use Testo\Sample\TestInline;

#[TestInline([1, 1], 2)]
#[TestInline([40, 2], 42)]
#[TestInline([-5, 5], 0)]
public function sum(int $a, int $b): int
{
    return $a + $b;
}
```

Each `TestInline` attribute runs the method with the given arguments and verifies the result. Simple as that.

`TestInline` works best with 2-10 static test cases where the expected behavior is self-evident from the input/output pairs. For larger test suites or cases that need explanation, consider writing a separate test in the `tests/` directory using `DataProvider`.

### Testing Private Methods

This is where `TestInline` really shows its value. Need to test a private helper? Just add the attribute:

```php
#[TestInline(['password123'], false)]  // too short
#[TestInline(['Password123!'], true)]  // valid
#[TestInline(['pass'], false)]  // no number
private function isStrongPassword(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}
```

The method stays private - you don't need to expose it or write reflection code yourself. Testo handles that.

### Named Arguments

Use named arguments for better readability:

```php
#[TestInline(['price' => 100.0, 'discount' => 0.1, 'tax' => 0.2], 108.0)]
#[TestInline(['price' => 50.0, 'discount' => 0.0, 'tax' => 0.1], 55.0)]
private function calculateFinalPrice(
    float $price,
    float $discount,
    float $tax
): float {
    return $price * (1 - $discount) * (1 + $tax);
}
```

### Custom Assertions with Closures

*Available in PHP 8.5+ (closures in attributes)*

For more complex checks, pass a closure as the second parameter:

```php
use Testo\Assert;

#[TestInline([10, 3], fn($r) => Assert::greaterThan(3, $r))]
public function divide(int $a, int $b): float
{
    return $a / $b;
}
```

The closure receives the actual result and can perform any assertions:

```php
#[TestInline(
    arguments: ['john.doe@example.com'],
    result: function (User $user) {
        Assert::same('john.doe@example.com', $user->email);
        Assert::true($user->isActive);
        Assert::notNull($user->createdAt);
    }
)]
public function createUser(string $email): User
{
    // ...
}
```