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

    /**
     * Object input is treated as a specimen — class, message and code must match,
     * but the actual exception is a different instance.
     */
    #[Test]
    public function equivalenceFromSpecimen(): never
    {
        Expect::exception(new \RuntimeException('boom', 42));

        throw new \RuntimeException('boom', 42);
    }

    /**
     * A subclass of the specimen's class is accepted (instanceof, not strict class match).
     */
    #[Test]
    public function equivalenceAcceptsSubclass(): never
    {
        Expect::exception(new \RuntimeException('boom', 42));

        throw new class('boom', 42) extends \RuntimeException {};
    }

    /**
     * Explicit withMessage overrides the auto-derived one from the specimen.
     */
    #[Test]
    public function equivalenceMessageOverride(): never
    {
        Expect::exception(new \RuntimeException('default', 7))
            ->withMessage('override');

        throw new \RuntimeException('override', 7);
    }

    /**
     * Explicit withCode overrides the auto-derived one from the specimen.
     */
    #[Test]
    public function equivalenceCodeOverride(): never
    {
        Expect::exception(new \RuntimeException('boom', 7))
            ->withCode(99);

        throw new \RuntimeException('boom', 99);
    }

    /**
     * `same: true` with an object input requires the very same instance to be thrown.
     */
    #[Test]
    public function sameInstance(): never
    {
        $expected = new \RuntimeException('boom', 42);
        Expect::exception($expected, same: true);

        throw $expected;
    }

    /**
     * `same: true` with a class-string requires exact class match — subclasses are rejected.
     */
    #[Test]
    public function strictClassMatch(): never
    {
        Expect::exception(\RuntimeException::class, same: true);

        throw new \RuntimeException('boom');
    }

    /**
     * A specimen with the default code (0) does not constrain the actual code —
     * any thrown code is accepted.
     */
    #[Test]
    public function specimenWithDefaultCodeDoesNotConstrainCode(): never
    {
        Expect::exception(new \RuntimeException('boom'));

        throw new \RuntimeException('boom', 999);
    }

    /**
     * A specimen with an empty message does not constrain the actual message —
     * any thrown message is accepted.
     */
    #[Test]
    public function specimenWithEmptyMessageDoesNotConstrainMessage(): never
    {
        Expect::exception(new \RuntimeException('', 42));

        throw new \RuntimeException('any message here', 42);
    }

    /**
     * Specimen with default code combined with explicit withCode — only the explicit value is enforced.
     */
    #[Test]
    public function specimenDefaultCodePlusExplicitWithCode(): never
    {
        Expect::exception(new \RuntimeException('boom'))
            ->withCode(99);

        throw new \RuntimeException('boom', 99);
    }

    /**
     * Multiple withCode calls — only the last one is in effect (replace semantics).
     */
    #[Test]
    public function withCodeReplacesPrevious(): never
    {
        Expect::exception(\RuntimeException::class)
            ->withCode(1)
            ->withCode(2)
            ->withCode([7, 99]);

        throw new \RuntimeException('', 99);
    }

    private function throwHelper(): never
    {
        throw new \RuntimeException('from helper');
    }
}
