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

    /** @var list<int>|null */
    private ?array $expectedCodes = null;

    private bool $expectNoPrevious = false;

    /** @var array{class-string|\Throwable, (callable(self): mixed)|null}|null */
    private ?array $expectedPrevious = null;

    /**
     * @param class-string|\Throwable $classOrObject Expected exception. The type-check mode is
     *        determined by the input type and {@see $strictClass}:
     *        - object → identity (`$actual === $classOrObject`),
     *        - class-string + `$strictClass = false` → `instanceof` (default),
     *        - class-string + `$strictClass = true` → exact class match (no subclasses).
     *        Equivalence comparison (instanceof + message + code) is built on top by
     *        {@see self::createEquals()} via {@see self::withMessage()} and {@see self::withCode()}.
     * @param bool $strictClass Only meaningful for class-string input; selects exact class match
     *        instead of `instanceof`.
     */
    private function __construct(
        public readonly string|\Throwable $classOrObject,
        private readonly bool $strictClass = false,
    ) {}

    /**
     * Create a handler in equivalence mode.
     *
     * Accepts a class-string (`instanceof` check) or an exception object treated as a specimen —
     * the actual exception must be of the same class and have the same message and code.
     *
     * Default values from the specimen (code `0`, message `''`) are treated as "not specified" and
     * are not enforced, so `Expect::exception(new Foo('msg'))->withCode(99)` works as expected
     * without the specimen's implicit `code=0` conflicting with the explicit `withCode(99)`.
     *
     * @param class-string|\Throwable $classOrObject Expected exception class, interface, or a specimen object.
     */
    public static function createEquals(string|\Throwable $classOrObject): self
    {
        if (\is_string($classOrObject)) {
            return new self($classOrObject);
        }

        $result = new self($classOrObject::class);
        $classOrObject->getCode() === 0 or $result->withCode($classOrObject->getCode());
        $classOrObject->getMessage() === '' or $result->withMessage($classOrObject->getMessage());
        return $result;
    }

    /**
     * Create a handler that performs the strictest comparison the input allows.
     *
     * - class-string ⇒ exact class match (subclasses rejected),
     * - object ⇒ identity (`===`, the very same instance must be thrown).
     *
     * @param class-string|\Throwable $classOrObject Expected exception class or the exact instance.
     */
    public static function createSame(string|\Throwable $classOrObject): self
    {
        return \is_string($classOrObject)
            ? new self($classOrObject, strictClass: true)
            : new self($classOrObject);
    }

    /**
     * The expected exception was thrown by the given method.
     *
     * @param class-string $class Fully qualified class name.
     * @param non-empty-string $method Method name.
     */
    #[\Override]
    public function fromMethod(string $class, string $method): static
    {
        $this->fromMethod[] = [$class, $method];
        return $this;
    }

    /**
     * The expected exception should have the exact message.
     */
    #[\Override]
    public function withMessage(string $message): static
    {
        $this->expectedMessage = $message;
        return $this;
    }

    #[\Override]
    public function withMessagePattern(string $pattern): static
    {
        $this->expectedMessagePattern = $pattern;
        return $this;
    }

    #[\Override]
    public function withMessageContaining(string $substring): static
    {
        $this->expectedMessageContaining[] = $substring;
        return $this;
    }

    #[\Override]
    public function withCode(int|array $code): static
    {
        $this->expectedCodes = (array) $code;
        return $this;
    }

    #[\Override]
    public function withoutPrevious(): static
    {
        $this->expectNoPrevious = true;
        return $this;
    }

    #[\Override]
    public function withPrevious(\Throwable|string $classOrObject, ?callable $assertion = null): static
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
        $isObject = \is_object($this->classOrObject);
        $class = $isObject ? $this->classOrObject::class : $this->classOrObject;
        $headline = match (true) {
            $isObject => 'the same ' . $class . ' instance is thrown',
            $this->strictClass => 'exactly ' . $class . ' is thrown',
            default => 'exception of type ' . $class . ' is thrown',
        };
        $composite = new ExpectationComposite(
            expectation: $headline,
            context: '',
        );

        # Type check: identity (object) | exact class (strictClass) | instanceof (default)
        if ($isObject) {
            if ($actual === $this->classOrObject) {
                $composite->success($headline);
            } else {
                $composite->fail(
                    expectation: $headline,
                    reason: $actual === null
                        ? 'none thrown'
                        : ($actual instanceof $class
                            ? 'got a different ' . $class . ' instance'
                            : 'got ' . $actual::class),
                );
                return $composite;
            }
        } elseif ($this->strictClass) {
            if ($actual !== null && $actual::class === $class) {
                $composite->success($headline);
            } else {
                $composite->fail(
                    expectation: $headline,
                    reason: $actual === null
                        ? 'none thrown'
                        : ($actual instanceof $class
                            ? 'got ' . $actual::class . ' (subclass of ' . $class . ')'
                            : 'got ' . $actual::class),
                );
                return $composite;
            }
        } elseif ($actual instanceof $class) {
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
        if ($this->expectedCodes !== null) {
            $code = $this->expectedCodes;
            $label = \count($code) === 1
                ? 'code is ' . $code[0]
                : 'code is one of [' . \implode(', ', $code) . ']';
            \in_array($actual->getCode(), $code, true)
                ? $composite->success($label)
                : $composite->fail(
                    expectation: $label,
                    reason: 'got ' . $actual->getCode(),
                );
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

        $prevMatched = $previous !== null && $previous instanceof $prevClass;

        if (!$prevMatched) {
            $composite->fail(
                expectation: 'has previous exception of type ' . $prevClass,
                reason: $previous === null ? 'no previous exception' : 'got ' . $previous::class,
            );
            return;
        }

        $composite->success('has previous exception of type ' . $prevClass);

        if ($callback !== null) {
            $subHandler = self::createEquals($prevClassOrObject);
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
