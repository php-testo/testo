<?php

declare(strict_types=1);

namespace Testo\Assert\Api;

/**
 * Expected exception API.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface ExpectedException
{
    /**
     * The expected exception was thrown by the given method.
     *
     * Does not mean that the exception was created in that method, only that the method was the point
     * where the exception was thrown.
     *
     * Can be called multiple times — all specified methods must be present in the exception's
     * stack trace for the assertion to pass.
     *
     * @param class-string $class Fully qualified class name.
     * @param string $method Method name.
     *
     * @note PHP captures the stack trace at the moment of exception object construction (`new \Exception()`),
     *       not at the `throw` statement. Therefore, the expected method must be in the call chain where
     *       the exception was instantiated.
     */
    public function fromMethod(string $class, string $method): static;

    /**
     * The expected exception should have the exact message.
     *
     * Subsequent calls replace the previous expectation.
     */
    public function withMessage(string $message): static;

    /**
     * The expected exception message should match the given pattern.
     *
     * Subsequent calls replace the previous expectation.
     *
     * @param non-empty-string $pattern Regex pattern.
     */
    public function withMessagePattern(string $pattern): static;

    /**
     * The expected exception message should contain the given substring.
     *
     * Can be called multiple times — the message must contain every specified substring.
     *
     * @param non-empty-string $substring Substring to search for.
     */
    public function withMessageContaining(string $substring): static;

    /**
     * The expected exception should have the given code or one of the given codes.
     *
     * Subsequent calls replace the previous expectation.
     *
     * @param int|list<int> $code Expected code or list of acceptable codes.
     */
    public function withCode(int|array $code): static;

    /**
     * The expected exception should not have a previous exception.
     *
     * Subsequent calls have no additional effect.
     */
    public function withoutPrevious(): static;

    /**
     * The expected exception was caused by the given previous exception.
     *
     * Subsequent calls replace the previous expectation.
     *
     * @param class-string|\Throwable $classOrObject Expected previous exception class, interface, or an object.
     * @param (callable(self): mixed)|null $assertion Optional assertion callback for the previous exception.
     */
    public function withPrevious(\Throwable|string $classOrObject, ?callable $assertion = null): static;
}
