<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

use Testo\Assert\Api\ExpectedException;
use Testo\Expect;
use Testo\Test;

/**
 * Stubs for negative {@see Expect::exception()} scenarios.
 */
final class ExpectExceptionNegative
{
    /**
     * No exception thrown at all.
     */
    #[Test]
    public function noneThrown(): void
    {
        Expect::exception(\RuntimeException::class);
    }

    /**
     * Wrong exception type.
     */
    #[Test]
    public function wrongType(): never
    {
        Expect::exception(\InvalidArgumentException::class);

        throw new \LogicException('wrong type');
    }

    /**
     * Wrong message.
     */
    #[Test]
    public function wrongMessage(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withMessage('expected message');

        throw new \RuntimeException('actual message');
    }

    /**
     * Message pattern does not match.
     */
    #[Test]
    public function wrongMessagePattern(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withMessagePattern('/^exact match$/');

        throw new \RuntimeException('not an exact match at all');
    }

    /**
     * Message does not contain the expected substring.
     */
    #[Test]
    public function wrongMessageContaining(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withMessageContaining('needle');

        throw new \RuntimeException('haystack without the word');
    }

    /**
     * Wrong code (single value).
     */
    #[Test]
    public function wrongCode(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withCode(42);

        throw new \RuntimeException('', 99);
    }

    /**
     * Wrong code (none of the listed codes match).
     */
    #[Test]
    public function wrongCodeArray(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withCode([1, 2, 3]);

        throw new \RuntimeException('', 99);
    }

    /**
     * Expected no previous, but there is one.
     */
    #[Test]
    public function withoutPreviousButHasOne(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withoutPrevious();

        throw new \RuntimeException('', 0, new \LogicException('previous'));
    }

    /**
     * Expected previous of a specific type, but got a different type.
     */
    #[Test]
    public function wrongPreviousType(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withPrevious(\InvalidArgumentException::class);

        throw new \RuntimeException('', 0, new \LogicException('wrong previous'));
    }

    /**
     * Previous type matches, but callback conditions fail.
     */
    #[Test]
    public function previousCallbackFails(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withPrevious(
                \LogicException::class,
                static fn(ExpectedException $ex) => $ex
                    ->withMessage('expected previous message')
                    ->withCode(100),
            );

        throw new \RuntimeException('', 0, new \LogicException('actual previous message', 999));
    }
}
