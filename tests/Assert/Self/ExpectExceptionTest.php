<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert\Api\ExpectedException;
use Testo\Expect;
use Testo\Test;
use Tests\Assert\Stub\ExceptionThrower;

/**
 * @see Expect::exception()
 */
final class ExpectExceptionTest
{
    #[Test]
    public function messageAndCode(): never
    {
        Expect::exception(\LogicException::class)
            ->withMessage('This is a logic exception')
            ->withCode(123)
            ->withCode([320, 123, 456])
            ->fromMethod(self::class, __FUNCTION__)
            ->withoutPrevious();

        throw new \LogicException('This is a logic exception', 123);
    }

    /**
     * Exception thrown directly in the test method — the test method itself is in the trace.
     */
    #[Test]
    public function fromMethodDirectThrow(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(self::class, __FUNCTION__);

        throw new \RuntimeException('direct');
    }

    /**
     * Exception thrown from a private helper — the helper method is in the trace.
     */
    #[Test]
    public function fromMethodSameClassHelper(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(self::class, 'throwHelper');

        $this->throwHelper();
    }

    /**
     * Exception thrown from an external class method.
     */
    #[Test]
    public function fromMethodExternalClass(): never
    {
        Expect::exception(\LogicException::class)
            ->fromMethod(ExceptionThrower::class, 'throw');

        ExceptionThrower::throw(\LogicException::class, 'external');
    }

    /**
     * Exception thrown deep in a call chain — checking the intermediate method.
     */
    #[Test]
    public function fromMethodNestedIntermediate(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(ExceptionThrower::class, 'delegateThrow');

        ExceptionThrower::delegateThrow(\RuntimeException::class, 'nested');
    }

    /**
     * Exception thrown deep in a call chain — checking the deepest entry point.
     */
    #[Test]
    public function fromMethodDeepNested(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(ExceptionThrower::class, 'deepThrow');

        ExceptionThrower::deepThrow(\RuntimeException::class, 'deep');
    }

    /**
     * Exception thrown deep in a call chain — checking the actual throw point.
     */
    #[Test]
    public function fromMethodDeepNestedThrowPoint(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(ExceptionThrower::class, 'throw');

        ExceptionThrower::deepThrow(\RuntimeException::class, 'deep throw point');
    }

    /**
     * Combining fromMethod with the test method when the throw goes through a helper.
     * The test method is also in the trace.
     */
    #[Test]
    public function fromMethodTestMethodInChain(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->fromMethod(self::class, __FUNCTION__);

        ExceptionThrower::throw(\InvalidArgumentException::class, 'chained');
    }

    /**
     * Multiple fromMethod calls — all must be present in the trace.
     */
    #[Test]
    public function fromMethodMultiple(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(ExceptionThrower::class, 'throw')
            ->fromMethod(ExceptionThrower::class, 'delegateThrow')
            ->fromMethod(ExceptionThrower::class, 'deepThrow');

        ExceptionThrower::deepThrow(\RuntimeException::class, 'multiple');
    }

    #[Test]
    public function messagePattern(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withMessagePattern('/^file .+ not found$/i');

        throw new \RuntimeException('File config.yml not found');
    }

    #[Test]
    public function messageContaining(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withMessageContaining('config')
            ->withMessageContaining('not found');

        throw new \RuntimeException('File config.yml not found');
    }

    #[Test]
    public function previous(): void
    {
        Expect::exception(\Throwable::class)
            ->withPrevious(
                \RuntimeException::class,
                static fn(ExpectedException $ex) => $ex
                    ->withCode(456)
                    ->withMessage('Previous exception')
                    ->withoutPrevious(),
            );

        $previous = new \RuntimeException('Previous exception', 456);
        throw new \LogicException('This is a logic exception', 123, $previous);
    }

    private function throwHelper(): never
    {
        throw new \RuntimeException('from helper');
    }
}
