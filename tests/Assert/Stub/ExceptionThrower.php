<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

/**
 * Stub for testing exception origin detection via stack trace.
 *
 * Exceptions must be created inside the methods (not passed in),
 * because PHP captures the trace at object construction time.
 */
final class ExceptionThrower
{
    /**
     * Creates and throws an exception directly.
     *
     * @param class-string<\Throwable> $class
     */
    public static function throw(string $class, string $message = ''): never
    {
        throw new $class($message);
    }

    /**
     * Calls {@see self::throw()} — one level of nesting.
     *
     * @param class-string<\Throwable> $class
     */
    public static function delegateThrow(string $class, string $message = ''): never
    {
        self::throw($class, $message);
    }

    /**
     * Calls through two layers before throwing.
     *
     * @param class-string<\Throwable> $class
     */
    public static function deepThrow(string $class, string $message = ''): never
    {
        self::delegateThrow($class, $message);
    }
}
