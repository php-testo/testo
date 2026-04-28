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
     * Expect that the test will throw an exception matching the given class/interface or specimen.
     *
     * The comparison forms a 2×2 matrix over input type and the `$same` flag:
     *
     * | input \ mode     | `$same = false` (default)         | `$same = true`                            |
     * |------------------|-----------------------------------|-------------------------------------------|
     * | **class-string** | `instanceof` check                | exact class match (subclasses rejected)   |
     * | **object**       | `instanceof` + message + code     | identity (`===`, the same instance)       |
     *
     * `$same` means "as strict as the input allows": for a class-string, the strictest check is
     * exact class equality; for an object, the strictest check is reference identity.
     *
     * For an object specimen with `$same = false`, default values (`code === 0`, empty message) are
     * treated as "not specified" and are not enforced — `Expect::exception(new Foo('msg'))->withCode(99)`
     * works as expected without the implicit zero conflicting with the explicit `99`.
     *
     * Additional constraints can be chained via the returned {@see ExpectedException}
     * (`withMessage`, `withCode`, `fromMethod`, etc.).
     *
     * @param class-string|\Throwable $classOrObject The expected exception class/interface, or a
     *        specimen object.
     * @param bool $same When true, perform the strictest comparison the input allows.
     *
     * @note Requires {@see ExpectationsInterceptor} to be registered.
     */
    public static function exception(
        string|\Throwable $classOrObject,
        bool $same = false,
    ): ExpectedException {
        return StaticState::expectException($classOrObject, $same);
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
