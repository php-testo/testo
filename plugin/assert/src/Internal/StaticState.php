<?php

declare(strict_types=1);

namespace Testo\Assert\Internal;

use Internal\Fiber\FiberLocal;
use Testo\Assert\Exception\StateNotFound;
use Testo\Assert\Internal\Expectation\ExpectedFail;
use Testo\Assert\Internal\Expectation\ExpectExceptionHandler;
use Testo\Assert\Internal\Expectation\Leaks;
use Testo\Assert\Internal\Expectation\NotLeaks;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\AssertionSuccess;
use Testo\Assert\State\Record;
use Testo\Assert\State\Test\Fail;
use Testo\Assert\TestState;

/**
 * Holds the current assertion collector.
 *
 * The active collector lives in a {@see FiberLocal} so each test fiber sees its own {@see TestState}:
 * while tests interleave on one event loop, {@see Assert}'s reads and writes stay isolated per fiber
 * without swapping a process-global slot at every suspension boundary.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final class StaticState
{
    /** @var FiberLocal<?TestState>|null */
    private static ?FiberLocal $active = null;

    /**
     * Install $state as the current collector for the current fiber, run $run, then restore the previous
     * collector. Replaces the former swap()/child-fiber trampoline.
     *
     * @template R
     * @param \Closure(): R $run
     * @return R
     */
    public static function scope(?TestState $state, \Closure $run): mixed
    {
        return self::store()->scope($state, $run);
    }

    /**
     * Get the current collector.
     */
    public static function current(): ?TestState
    {
        return self::store()->get();
    }

    /**
     * Success type assertion.
     *
     * @param non-empty-string $type The expected type.
     */
    public static function typeSuccess(string $type, mixed $actual, string $message = ''): AssertionComposite
    {
        $result = new AssertionComposite(Support::stringify($actual), "is $type", $message);
        $state = self::current();
        $state === null or $state->history[] = $result;
        return $result;
    }

    /**
     * Register a type assertion failure and throw an exception.
     *
     * @param non-empty-string $type The expected type.
     */
    public static function typeFail(string $type, mixed $actual, string $message = ''): never
    {
        $result = new AssertionException(
            value: Support::stringify($actual),
            assertion: "is $type",
            context: $message,
            reason: "got " . Support::stringify($actual),
            details: '',
        );
        $state = self::current();
        $state === null or $state->history[] = $result;
        throw $result;
    }

    /**
     * @param mixed $value The actual value that was asserted.
     * @param non-empty-string $assertion The assertion result (e.g., "is empty", "contains 'foo'").
     * @param string $context Optional user-provided context describing what is being asserted.
     */
    public static function success(mixed $value, string $assertion, string $context = ''): void
    {
        $state = self::current();
        $state === null or $state->history[] = new AssertionSuccess(
            value: Support::stringify($value),
            assertion: $assertion,
            context: $context,
        );
    }

    /**
     * Log a failed assertion and throw the given exception.
     *
     * @template T
     * @param T $failure The exception to throw.
     * @throws T
     */
    public static function fail(Record&\Throwable $failure): never
    {
        $state = self::current();
        $state === null or $state->history[] = $failure;
        throw $failure;
    }

    /**
     * Set the expected exception for the current test.
     *
     * @param class-string|\Throwable $classOrObject The expected exception class, interface, or an exception object.
     * @param bool $same Selects the strictest comparison the input allows:
     *        - class-string + `false` → `instanceof` (default),
     *        - class-string + `true` → exact class match (no subclasses),
     *        - object + `false` → `instanceof` + message + code,
     *        - object + `true` → identity (`===`).
     *
     * @throws \RuntimeException when there is no current {@see TestState}.
     */
    public static function expectException(
        string|\Throwable $classOrObject,
        bool $same = false,
    ): ExpectExceptionHandler {
        $state = self::current() ?? throw new StateNotFound();

        return $state->expectations[] = $same
            ? ExpectExceptionHandler::createSame($classOrObject)
            : ExpectExceptionHandler::createEquals($classOrObject);
    }

    public static function expectFail(Fail $exception): void
    {
        $state = self::current() ?? throw new StateNotFound();
        $state->expectations[] = new ExpectedFail($exception);
    }

    /**
     * Track the given objects in the current test state to detect memory leaks.
     *
     * @param bool $notLeaks Whether to expect no leaks (true) or expect leaks (false).
     * @param object ...$objects The objects to track.
     * @return NotLeaks The created expectation.
     */
    public static function trackObjectsLeak(bool $notLeaks, object ...$objects): Leaks|NotLeaks
    {
        $state = self::current() ?? throw new StateNotFound();
        return $state->expectations[] = $notLeaks
            ? new NotLeaks(...$objects)
            : new Leaks(...$objects);
    }

    /**
     * @return FiberLocal<?TestState>
     */
    private static function store(): FiberLocal
    {
        return self::$active ??= new FiberLocal(null);
    }
}
