<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Expectation;

use Testo\Assert\Api\ExpectedException;
use Testo\Assert\State\Expectation;
use Testo\Assert\State\Expectation\ExpectationComposite;
use Testo\Assert\TestState;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;

/**
 * Expected exception declaration.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final class ExpectExceptionHandler implements ExpectedException
{
    /**
     * @var list<array{class-string, non-empty-string}> Expected origin methods [class, method].
     */
    private array $fromMethod = [];

    private ?string $expectedMessage = null;

    /** @var non-empty-string|null */
    private ?string $expectedMessagePattern = null;

    /** @var list<non-empty-string> */
    private array $expectedMessageContaining = [];

    /** @var list<int|list<int>> */
    private array $expectedCodes = [];

    private bool $expectNoPrevious = false;

    /** @var array{class-string|\Throwable, (callable(self): mixed)|null}|null */
    private ?array $expectedPrevious = null;

    /**
     * @param class-string|\Throwable $classOrObject Expected exception class, interface, or an object.
     */
    public function __construct(
        public readonly string|\Throwable $classOrObject,
    ) {}

    /**
     * The expected exception was thrown by the given method.
     *
     * @param class-string $class Fully qualified class name.
     * @param non-empty-string $method Method name.
     */
    #[\Override]
    public function fromMethod(string $class, string $method): self
    {
        $this->fromMethod[] = [$class, $method];
        return $this;
    }

    /**
     * The expected exception should have the exact message.
     */
    #[\Override]
    public function withMessage(string $message): self
    {
        $this->expectedMessage = $message;
        return $this;
    }

    #[\Override]
    public function withMessagePattern(string $pattern): self
    {
        $this->expectedMessagePattern = $pattern;
        return $this;
    }

    #[\Override]
    public function withMessageContaining(string $substring): self
    {
        $this->expectedMessageContaining[] = $substring;
        return $this;
    }

    #[\Override]
    public function withCode(int|array $code): self
    {
        $this->expectedCodes[] = $code;
        return $this;
    }

    #[\Override]
    public function withoutPrevious(): self
    {
        $this->expectNoPrevious = true;
        return $this;
    }

    #[\Override]
    public function withPrevious(\Throwable|string $classOrObject, ?callable $assertion = null): self
    {
        $this->expectedPrevious = [$classOrObject, $assertion];
        return $this;
    }

    public function __invoke(TestResult $result, TestState $state): TestResult
    {
        $record = $this->evaluate($result->failure);
        $state->history[] = $record;

        return $record->isSuccess()
            ? $result->with(status: Status::Passed)
            : $result->with(status: Status::Failed)->withFailure($record);
    }

    private function evaluate(?\Throwable $actual): Expectation|\Throwable
    {
        $class = \is_string($this->classOrObject) ? $this->classOrObject : $this->classOrObject::class;
        $composite = new ExpectationComposite(
            expectation: 'exception of type ' . $class . ' is thrown',
            context: '',
        );

        # Type check
        $typeMatched = \is_object($this->classOrObject)
            ? ($actual === $this->classOrObject)
            : ($actual instanceof $class);

        if ($typeMatched) {
            $composite->success($class === $actual::class
                ? $class . ' is thrown'
                : $actual::class . ' is thrown as an instance of ' . $class);
        } else {
            $composite->fail(
                expectation: $class . ' is thrown',
                reason: $actual === null ? 'none thrown' : 'got ' . $actual::class,
            );
            return $composite;
        }

        # fromMethod check
        foreach ($this->fromMethod as [$expectedClass, $expectedMethod]) {
            $label = \sprintf('thrown from %s::%s()', $expectedClass, $expectedMethod);
            if ($this->isThrownFrom($actual, $expectedClass, $expectedMethod)) {
                $composite->success($label);
            } else {
                $composite->fail(
                    expectation: $label,
                    reason: 'not found in the stack trace',
                );
            }
        }

        # Message check
        if ($this->expectedMessage !== null) {
            $actual->getMessage() === $this->expectedMessage
                ? $composite->success('message is "' . $this->expectedMessage . '"')
                : $composite->fail(
                    expectation: 'message is "' . $this->expectedMessage . '"',
                    reason: 'got "' . $actual->getMessage() . '"',
                );
        }

        # Message pattern check
        if ($this->expectedMessagePattern !== null) {
            \preg_match($this->expectedMessagePattern, $actual->getMessage()) === 1
                ? $composite->success('message matches pattern ' . $this->expectedMessagePattern)
                : $composite->fail(
                    expectation: 'message matches pattern ' . $this->expectedMessagePattern,
                    reason: 'got "' . $actual->getMessage() . '"',
                );
        }

        # Message containing check
        foreach ($this->expectedMessageContaining as $substring) {
            \str_contains($actual->getMessage(), $substring)
                ? $composite->success('message contains "' . $substring . '"')
                : $composite->fail(
                    expectation: 'message contains "' . $substring . '"',
                    reason: 'got "' . $actual->getMessage() . '"',
                );
        }

        # Code check
        foreach ($this->expectedCodes as $code) {
            if (\is_array($code)) {
                \in_array($actual->getCode(), $code, true)
                    ? $composite->success('code is one of [' . \implode(', ', $code) . ']')
                    : $composite->fail(
                        expectation: 'code is one of [' . \implode(', ', $code) . ']',
                        reason: 'got ' . $actual->getCode(),
                    );
            } else {
                $actual->getCode() === $code
                    ? $composite->success('code is ' . $code)
                    : $composite->fail(
                        expectation: 'code is ' . $code,
                        reason: 'got ' . $actual->getCode(),
                    );
            }
        }

        # Previous exception checks
        if ($this->expectNoPrevious) {
            $actual->getPrevious() === null
                ? $composite->success('has no previous exception')
                : $composite->fail(
                    expectation: 'has no previous exception',
                    reason: 'got ' . $actual->getPrevious()::class,
                );
        }

        if ($this->expectedPrevious !== null) {
            $this->evaluatePrevious($composite, $actual);
        }

        return $composite;
    }

    private function evaluatePrevious(ExpectationComposite $composite, \Throwable $actual): void
    {
        [$prevClassOrObject, $callback] = $this->expectedPrevious;
        $previous = $actual->getPrevious();
        $prevClass = \is_string($prevClassOrObject) ? $prevClassOrObject : $prevClassOrObject::class;

        $prevMatched = $previous !== null && (
            \is_object($prevClassOrObject)
                ? ($previous === $prevClassOrObject)
                : ($previous instanceof $prevClass)
        );

        if (!$prevMatched) {
            $composite->fail(
                expectation: 'has previous exception of type ' . $prevClass,
                reason: $previous === null ? 'no previous exception' : 'got ' . $previous::class,
            );
            return;
        }

        $composite->success('has previous exception of type ' . $prevClass);

        if ($callback !== null) {
            $subHandler = new self($prevClassOrObject);
            $callback($subHandler);
            $subResult = $subHandler->evaluate($previous);

            foreach ($subResult->getRecords() as $record) {
                $record->isSuccess()
                    ? $composite->success($record->getExpectation())
                    : $composite->fail(
                        expectation: $record->getExpectation(),
                        reason: $record->getFailReason(),
                    );
            }
        }
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private function isThrownFrom(\Throwable $exception, string $class, string $method): bool
    {
        foreach ($exception->getTrace() as $frame) {
            if (($frame['class'] ?? null) === $class
                && ($frame['function'] ?? null) === $method
            ) {
                return true;
            }
        }

        return false;
    }
}
