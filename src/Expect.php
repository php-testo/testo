<?php

declare(strict_types=1);

namespace Testo;

use Testo\Assert\Api\ExpectedException;
use Testo\Assert\Internal\Expectation\Leaks;
use Testo\Assert\Internal\Expectation\NotLeaks;
use Testo\Assert\Internal\Middleware\ExpectationsInterceptor;
use Testo\Assert\Internal\StaticState;

/**
 * Expectations for the test execution.
 *
 * @api
 */
final class Expect
{
    /**
     * Expect that the test will throw the given exception object or an exception of the given class/interface.
     *
     * When an object is passed, it is treated as a specimen — the actual exception must be of the same
     * class (instanceof) and have the same message and code. Use {@see self::sameException()} when the
     * exact same instance must propagate.
     *
     * @param class-string|\Throwable $classOrObject The expected exception class, interface, or a
     *        specimen object describing the exception to match.
     *
     * @note Requires {@see ExpectationsInterceptor} to be registered.
     */
    public static function exception(
        string|\Throwable $classOrObject,
    ): ExpectedException {
        return StaticState::expectException($classOrObject);
    }

    /**
     * Expect that the test will throw the very same exception instance as the given one.
     *
     * Useful for verifying that an exception propagates unchanged through middleware, decorators,
     * or rethrow points without being replaced by an equivalent copy.
     *
     * @param \Throwable $exception The exact instance that must be thrown.
     *
     * @note Requires {@see ExpectationsInterceptor} to be registered.
     */
    public static function sameException(\Throwable $exception): ExpectedException
    {
        return StaticState::expectException($exception, identity: true);
    }

    /**
     * Expect that the given objects are not cached in memory after the test execution.
     *
     * Note that PHP may skip collecting objects if the test ends with throwing an exception.
     * Also, there are issues about garbage collecting on MacOS.
     *
     * @param object ...$objects The objects to monitor for memory leaks.
     */
    public static function notLeaks(object ...$objects): NotLeaks
    {
        return StaticState::trackObjectsLeak(true, ...$objects);
    }

    /**
     * Expect that the given objects are cached in memory after the test execution.
     *
     * @param object ...$objects The objects to monitor for memory leaks.
     */
    public static function leaks(object ...$objects): Leaks
    {
        return StaticState::trackObjectsLeak(false, ...$objects);
    }
}
