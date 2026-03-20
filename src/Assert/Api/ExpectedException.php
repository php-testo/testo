<?php

declare(strict_types=1);

namespace Testo\Assert\Api;

/**
 * Expected exception API.
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
    public function fromMethod(string $class, string $method): self;

    /**
     * The expected exception should have the exact message.
     */
    public function withMessage(string $message): self;

    /**
     * The expected exception message should match the given pattern.
     *
     * @param non-empty-string $pattern Regex pattern.
     */
    public function withMessagePattern(string $pattern): self;

    /**
     * The expected exception message should contain the given substring.
     *
     * @param non-empty-string $substring Substring to search for.
     */
    public function withMessageContaining(string $substring): self;

    /**
     * The expected exception should have the given code or one of the given codes.
     *
     * @param int|list<int> $code Expected code or list of expected codes.
     */
    public function withCode(int|array $code): self;

    /**
     * The expected exception should not have a previous exception.
     */
    public function withoutPrevious(): self;

    /**
     * The expected exception was caused by the given previous exception.
     *
     * @param class-string|\Throwable $classOrObject Expected previous exception class, interface, or an object.
     * @param (callable(self): mixed)|null $assertion Optional assertion callback for the previous exception.
     */
    public function withPrevious(\Throwable|string $classOrObject, ?callable $assertion = null): self;
}
