<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for callables.
 *
 * The checks read the reflection of what the callable points to: the closure, the function, the
 * method of a `[$object, 'method']` / `[Foo::class, 'method']` array or a `'Foo::method'` string,
 * or `__invoke()` of an invokable object.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface CallableType
{
    /**
     * Asserts that the callable cannot be bound to an object.
     *
     * A closure declared `static`, a static method, and a plain function (a function name or a
     * first-class callable of a function) are static. An instance method, an invokable object, and a
     * closure not declared `static` are not, even when the closure was created outside a class.
     *
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function isStatic(string $message = ''): static;

    /**
     * Asserts that the callable is not static, as defined by {@see self::isStatic()}.
     *
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function notStatic(string $message = ''): static;

    /**
     * Asserts that the callable declares the given return type.
     *
     * The declared type is compared as written, ignoring the order of union and intersection
     * members, letter case, a leading backslash and whitespace; `?string` equals `string|null`.
     * Types are not resolved: `int` does not match `int|string`, and `self` does not match the
     * class name. An internal method without a declared type is compared by its tentative type.
     * A callable with no return type fails.
     *
     * @param non-empty-string $type The expected return type, e.g. `?string` or `Foo&Bar`.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function hasReturnType(string $type, string $message = ''): static;
}
