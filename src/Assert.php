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
use Testo\Assert\Internal\Assertion\AssertFloat;
use Testo\Assert\Internal\Assertion\AssertInt;
use Testo\Assert\Internal\Assertion\AssertJson;
use Testo\Assert\Internal\Assertion\AssertObject;
use Testo\Assert\Internal\Assertion\AssertString;
use Testo\Assert\Internal\Assertion\AssertArray;
use Testo\Assert\State\AssertException;
use Testo\Assert\State\AssertTypeFailure;
use Testo\Assert\StaticState;
use Testo\Assert\Support;
use Testo\Assert\Internal\Assertion\AssertIterable;

/**
 * Assertion utilities.
 */
final class Assert
{
    /**
     * Asserts that two values are the same (identical).
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value to compare against the expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        $actual === $expected
            ? StaticState::log('Assert same: `' . Support::stringify($expected) . '`', $message)
            : StaticState::fail(AssertException::compare($expected, $actual, $message));
    }

    /**
     * Asserts that two values are the not same (not identical).
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value to compare against the expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function notSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $actual !== $expected
            ? StaticState::log('Assert not same: `' . Support::stringify($expected) . '`', $message)
            : StaticState::fail(AssertException::compare(
                $expected,
                $actual,
                $message,
                pattern: 'Failed asserting that `%s` is not identical to `%s`',
                showDiff: false,
            ));
    }

    /**
     * Asserts that two values are equal (not strict).
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value to compare against the expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function equals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $actual == $expected
            ? StaticState::log('Assert equals: `' . Support::stringify($expected) . '`', $message)
            : StaticState::fail(AssertException::compare(
                $expected,
                $actual,
                $message,
                pattern: 'Failed asserting that `%1s` is equals to `%2s`',
            ));
    }

    /**
     * Asserts that two values are not equal (not strict).
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value to compare against the expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function notEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $actual != $expected
            ? StaticState::log('Assert not equals: `' . Support::stringify($expected) . '`', $message)
            : StaticState::fail(AssertException::compare(
                $expected,
                $actual,
                $message,
                pattern: 'Failed asserting that `%1s` is not equals to `%2s`',
                showDiff: false,
            ));
    }

    /**
     * Asserts that the condition is true.
     *
     * @param bool $condition The condition asserting to be true.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function true(bool $condition, string $message = ''): void
    {
        $condition === true
            ? StaticState::log('Assert true', $message)
            : StaticState::fail(AssertException::compare(
                true,
                $condition,
                $message,
                'Failed asserting that value `%2$s` is `%1$s`',
            ));
    }

    /**
     * Asserts that the condition is false.
     *
     * @param bool $condition The condition asserting to be false.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function false(bool $condition, string $message = ''): void
    {
        $condition === false
            ? StaticState::log('Assert false', $message)
            : StaticState::fail(AssertException::compare(
                false,
                $condition,
                $message,
                'Failed asserting that value `%2$s` is `%1$s`',
            ));
    }

    /**
     * Asserts that the actual object is an instance of the expected class/interface.
     *
     * @template ExpectedType
     *
     * @param class-string<ExpectedType> $expected Fully-qualified class or interface name.
     * @param mixed $actual The actual object to check.
     * @param string $message Optional message for the assertion.
     * @throws AssertException when the assertion fails.
     *
     * @psalm-assert ExpectedType $actual
     * @phpstan-assert ExpectedType $actual
     */
    public static function instanceOf(string $expected, mixed $actual, string $message = ''): ObjectType
    {
        return AssertObject::validateAndCreate($actual)->instanceOf($expected, $message);
    }

    /**
     * Asserts that given collection contains expected value.
     *
     * @param mixed $needle The expected value.
     * @param iterable $haystack Iterable (array or Traversable) to search in.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function contains(mixed $needle, iterable $haystack, string $message = ''): void
    {
        foreach ($haystack as $element) {
            if ($needle === $element) {
                StaticState::log('Assert contains', $message);
                return;
            }
        }
        StaticState::fail(AssertException::compare(
            $needle,
            $haystack,
            $message,
            'Failed asserting that `%2$s` contains `%1$s`',
        ));
    }

    /**
     * Asserts that the given value is null.
     *
     * @param mixed $actual The actual value to check for null.
     * @param string $message Short description about what exactly is being asserted.
     * @throws AssertException when the assertion fails.
     */
    public static function null(
        mixed $actual,
        string $message = '',
    ): void {
        $actual === null
            ? StaticState::log('Assert `null`', $message)
            : StaticState::fail(AssertException::compare(null, $actual, $message));
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
     * @throws AssertException when the assertion fails.
     */
    public static function blank(
        mixed $actual,
        string $message = '',
    ): void {
        if (
            $actual === null || $actual === '' || $actual === [] || ($actual instanceof \Countable && \count($actual) === 0)
        ) {
            StaticState::log('Assert blank', $message);
            return;
        }
        StaticState::fail(AssertException::compare(
            null,
            $actual,
            $message,
            'Failed asserting that `%2$s` does not contain any data',
        ));
    }

    /**
     * Asserts that the given value is of `string` data type.
     *
     * @throws AssertException when the assertion fails.
     */
    public static function string(mixed $actual): StringType
    {
        return AssertString::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `int` data type.
     *
     * @throws AssertTypeFailure
     */
    public static function int(mixed $actual): IntType
    {
        return AssertInt::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `float` data type.
     *
     * @throws AssertTypeFailure
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
     * @throws AssertTypeFailure
     *
     * @deprecated To be implemented
     */
    public static function numeric(mixed $actual): NumericType
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Asserts that the given string is a valid JSON.
     *
     * @param string $actual The actual JSON string to check.
     * @throws AssertException when the assertion fails.
     *
     * @deprecated To be implemented
     */
    public static function json(string $actual): JsonAbstract
    {
        return new AssertJson($actual);
    }

    /**
     * Asserts that the given value is of `iterable` data type.
     *
     * Iterables include arrays and objects implementing iterable interface.
     * Does not work with Generators.
     *
     * @throws AssertTypeFailure
     */
    public static function iterable(mixed $actual): IterableType
    {
        return AssertIterable::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `array` data type.
     *
     * @throws AssertTypeFailure
     */
    public static function array(mixed $actual): ArrayType
    {
        return AssertArray::validateAndCreate($actual);
    }

    /**
     * Asserts that the given value is of `object` data type.
     *
     * @throws AssertTypeFailure
     *
     * @deprecated To be implemented
     */
    public static function object(mixed $actual): ObjectType
    {
        throw new \LogicException('Not implemented yet');
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
     * @throws AssertException
     */
    public static function fail(string $message = ''): never
    {
        $exception = AssertException::fail($message);
        StaticState::expectFail($exception);
        StaticState::fail($exception);
    }
}
