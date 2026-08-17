<?php

declare(strict_types=1);

namespace Testo;

use Testo\Assert\Api\Builtin\ArrayType;
use Testo\Assert\Api\Builtin\FloatType;
use Testo\Assert\Api\Builtin\IntType;
use Testo\Assert\Api\Builtin\IterableType;
use Testo\Assert\Api\Builtin\NumericType;
use Testo\Assert\Api\Builtin\ObjectType;
use Testo\Assert\Api\Builtin\StringType;
use Testo\Assert\Api\Json\JsonAbstract;
use Testo\Assert\Internal\Assertion\AssertArray;
use Testo\Assert\Internal\Assertion\AssertFloat;
use Testo\Assert\Internal\Assertion\AssertInt;
use Testo\Assert\Internal\Assertion\AssertIterable;
use Testo\Assert\Internal\Assertion\AssertJson;
use Testo\Assert\Internal\Assertion\AssertNumeric;
use Testo\Assert\Internal\Assertion\AssertObject;
use Testo\Assert\Internal\Assertion\AssertString;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\Internal\Support;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Assert\State\Test\Fail;
use Testo\Common\Attribute\AssertMethod;

/**
 * Assertion utilities.
 *
 * @api
 */
final class Assert
{
    /**
     * Asserts that two values are the same (identical).
     *
     * @template ExpectedType
     *
     * @param mixed $actual The actual value to compare against the expected value.
     * @param ExpectedType $expected The expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert =ExpectedType $actual
     * @phpstan-assert =ExpectedType $actual
     */
    #[AssertMethod]
    public static function same(mixed $actual, mixed $expected, string $message = ''): void
    {
        $actual === $expected
            ? StaticState::success($actual, 'is the same', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: $expected,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'is the same as `' . Support::stringify($expected) . '`',
                context: $message,
                reason: 'expected `' . Support::stringify($expected) . '`, got `' . Support::stringify($actual) . '`',
            ));
    }

    /**
     * Asserts that two values are the not same (not identical).
     *
     * @param mixed $actual The actual value to compare against the expected value.
     * @param mixed $expected The expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    public static function notSame(mixed $actual, mixed $expected, string $message = ''): void
    {
        $actual !== $expected
            ? StaticState::success($actual, 'is not same as `' . Support::stringify($expected) . '`', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: $expected,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'is not the same as `' . Support::stringify($expected) . '`',
                context: $message,
                reason: 'both values are identical',
            ));
    }

    /**
     * Asserts that two values are equal (not strict).
     *
     * @param mixed $actual The actual value to compare against the expected value.
     * @param mixed $expected The expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    public static function equals(mixed $actual, mixed $expected, string $message = ''): void
    {
        $actual == $expected
            ? StaticState::success($actual, 'equals to `' . Support::stringify($expected) . '`', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: $expected,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'equals to `' . Support::stringify($expected) . '`',
                context: $message,
                reason: 'expected `' . Support::stringify($expected) . '`, got `' . Support::stringify($actual) . '`',
            ));
    }

    /**
     * Asserts that two values are not equal (not strict).
     *
     * @param mixed $actual The actual value to compare against the expected value.
     * @param mixed $expected The expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    public static function notEquals(mixed $actual, mixed $expected, string $message = ''): void
    {
        $actual != $expected
            ? StaticState::success($actual, 'is not equals to `' . Support::stringify($expected) . '`', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: $expected,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'is not equals to `' . Support::stringify($expected) . '`',
                context: $message,
                reason: 'both values are equal',
            ));
    }

    /**
     * Asserts that the value is true.
     *
     * @param mixed $actual The actual value to check.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert true $actual
     * @phpstan-assert true $actual
     */
    #[AssertMethod]
    public static function true(mixed $actual, string $message = ''): void
    {
        $actual === true
            ? StaticState::success($actual, 'is exactly `true`', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: true,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'is exactly `true`',
                context: $message,
                reason: 'expected `true`, got `' . Support::stringify($actual) . '`',
            ));
    }

    /**
     * Asserts that the value is false.
     *
     * @param mixed $actual The actual value to check.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert false $actual
     * @phpstan-assert false $actual
     */
    #[AssertMethod]
    public static function false(mixed $actual, string $message = ''): void
    {
        $actual === false
            ? StaticState::success($actual, 'is exactly `false`', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: false,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'is exactly `false`',
                context: $message,
                reason: 'expected `false`, got `' . Support::stringify($actual) . '`',
            ));
    }

    /**
     * Asserts that the actual object is an instance of the expected class/interface.
     *
     * @template ExpectedType
     *
     * @param mixed $actual The actual object to check.
     * @param class-string<ExpectedType> $expected Fully-qualified class or interface name.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert ExpectedType $actual
     * @phpstan-assert ExpectedType $actual
     */
    public static function instanceOf(mixed $actual, string $expected, string $message = ''): ObjectType
    {
        return AssertObject::validateAndCreate($actual)->instanceOf($expected, $message);
    }

    /**
     * Asserts that given collection contains expected value.
     *
     * @param iterable $haystack Iterable (array or Traversable) to search in.
     * @param mixed $needle The expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     */
    public static function contains(iterable $haystack, mixed $needle, string $message = ''): void
    {
        foreach ($haystack as $element) {
            if ($needle === $element) {
                StaticState::success($haystack, 'contains `' . Support::stringify($needle) . '`', $message);
                return;
            }
        }

        StaticState::fail(new ComparisonFailure(
            expected: $needle,
            actual: $haystack,
            value: Support::stringify($haystack),
            assertion: 'contains `' . Support::stringify($needle) . '`',
            context: $message,
            reason: '`' . Support::stringify($needle) . '` not found',
        ));
    }

    /**
     * Asserts that the given value is null.
     *
     * @param mixed $actual The actual value to check for null.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert null $actual
     * @phpstan-assert null $actual
     */
    #[AssertMethod]
    public static function null(
        mixed $actual,
        string $message = '',
    ): void {
        $actual === null
            ? StaticState::success($actual, 'is exactly `null`', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: null,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'is exactly `null`',
                context: $message,
                reason: 'expected `null`, got `' . Support::stringify($actual) . '`',
            ));
    }

    /**
     * Asserts that the given value is not null.
     *
     * @param mixed $actual The actual value to check for non-null.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert !null $actual
     * @phpstan-assert !null $actual
     */
    #[AssertMethod]
    public static function notNull(
        mixed $actual,
        string $message = '',
    ): void {
        $actual !== null
            ? StaticState::success($actual, 'is not `null`', $message)
            : StaticState::fail(new ComparisonFailure(
                expected: 'non-null',
                actual: $actual,
                value: Support::stringify($actual),
                assertion: 'is not `null`',
                context: $message,
                reason: 'expected a non-null value, got `null`',
            ));
    }

    /**
     * Checks if a value is blank.
     *
     * Successful only for values representing absence of data:
     * null, empty string, empty array or countable object with no elements.
     *
     * Unlike empty(), does not consider false, 0, and "0" as blank,
     * since they represent valid data.
     * @param mixed $actual The actual value to check for blank.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    public static function blank(
        mixed $actual,
        string $message = '',
    ): void {
        if (
            $actual === null || $actual === '' || $actual === [] || ($actual instanceof \Countable && \count($actual) === 0)
        ) {
            StaticState::success($actual, 'is blank', $message);
            return;
        }

        StaticState::fail(new AssertionException(
            value: Support::stringify($actual),
            assertion: 'is blank',
            context: $message,
            reason: 'value contains data',
            details: '',
        ));
    }

    /**
     * Asserts that the value is not blank.
     *
     * The inverse of {@see self::blank()}: fails only for values representing absence of data —
     * null, empty string, empty array or a Countable object with no elements. As with blank(),
     * false, 0 and "0" count as valid (non-blank) data, unlike PHP's empty().
     *
     * @param mixed $actual The actual value to check for non-blank.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    public static function notBlank(
        mixed $actual,
        string $message = '',
    ): void {
        $actual === null || $actual === '' || $actual === [] || ($actual instanceof \Countable && \count($actual) === 0)
            and StaticState::fail(new AssertionException(
                value: Support::stringify($actual),
                assertion: 'is not blank',
                context: $message,
                reason: 'value is blank',
                details: '',
            ));

        StaticState::success($actual, 'is not blank', $message);
    }

    /**
     * Asserts that the given value has the expected count.
     *
     * @param \Countable|iterable $actual The actual value to check for count.
     * @param int $expected The expected count.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    public static function count(\Countable|iterable $actual, int $expected, string $message = ''): void
    {
        if ($actual instanceof \Countable) {
            $count = \count($actual);
            if ($count === $expected) {
                StaticState::success($actual, "A \Countable has count `$expected`", $message);
                return;
            }

            StaticState::fail(new ComparisonFailure(
                expected: $expected,
                actual: $actual,
                value: Support::stringify($actual),
                assertion: "has count `$expected`",
                context: $message,
                reason: "expected `$expected`, got `$count`",
            ));
        }

        AssertIterable::validateAndCreate($actual)->hasCount($expected);
    }

    /**
     * Asserts that the given value is of `string` data type.
     *
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert string $actual
     * @phpstan-assert string $actual
     */
    #[AssertMethod]
    public static function string(mixed $actual): StringType
    {
        return AssertString::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `int` data type.
     *
     * @throws AssertionException
     *
     * @psalm-assert int $actual
     * @phpstan-assert int $actual
     */
    public static function int(mixed $actual): IntType
    {
        return AssertInt::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `float` data type.
     *
     * @throws AssertionException
     *
     * @psalm-assert float $actual
     * @phpstan-assert float $actual
     */
    public static function float(mixed $actual): FloatType
    {
        return AssertFloat::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `numeric` data type.
     *
     * Numeric type includes integer, float, and numeric strings.
     *
     * @throws AssertionException
     *
     * @psalm-assert int|float|numeric-string $actual
     * @phpstan-assert int|float|numeric-string $actual
     */
    public static function numeric(mixed $actual): NumericType
    {
        return AssertNumeric::validateAndCreate($actual);
    }

    /**
     * Asserts that the given string is a valid JSON.
     *
     * @param string $actual The actual JSON string to check.
     * @throws AssertionException when the value is not valid JSON.
     */
    public static function json(string $actual): JsonAbstract
    {
        return AssertJson::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `iterable` data type.
     *
     * Iterables include arrays and objects implementing iterable interface.
     * Does not work with Generators.
     *
     * @throws AssertionException
     *
     * @psalm-assert iterable $actual
     * @phpstan-assert iterable<mixed> $actual
     */
    public static function iterable(mixed $actual): IterableType
    {
        return AssertIterable::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `array` data type.
     *
     * @throws AssertionException
     *
     * @psalm-assert array $actual
     * @phpstan-assert array<mixed, mixed> $actual
     */
    public static function array(mixed $actual): ArrayType
    {
        return AssertArray::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `object` data type.
     *
     * @throws AssertionException
     *
     * @psalm-assert object $actual
     * @phpstan-assert object $actual
     */
    public static function object(mixed $actual): ObjectType
    {
        return AssertObject::validateAndCreate($actual);
    }

    /**
     * Fails the test.
     *
     * Use this method to explicitly indicate that the test should end in failure.
     *
     * If this method is called, it is expected that the test will end with an exception thrown by this method.
     * If the test catches this exception and continues execution, it will be marked as Risky.
     *
     * @param string $message The reason for the failure.
     * @throws Fail
     */
    #[AssertMethod]
    public static function fail(string $message = ''): never
    {
        $exception = new Fail($message);
        StaticState::expectFail($exception);
        StaticState::fail($exception);
    }
}
