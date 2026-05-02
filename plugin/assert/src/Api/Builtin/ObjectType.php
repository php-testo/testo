<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for objects.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface ObjectType
{
    /**
     * Asserts that the object is an instance of the given class/interface.
     *
     * @template ExpectedType
     *
     * @param class-string<ExpectedType> $expected Fully-qualified class or interface name.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     *
     * @psalm-assert ExpectedType $actual
     * @phpstan-assert ExpectedType $actual
     */
    public function instanceOf(string $expected, string $message = ''): static;

    /**
     * Asserts that the object has the given property.
     *
     * @param non-empty-string $propertyName The property name to check.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     *
     */
    public function hasProperty(string $propertyName, string $message = ''): static;
}
