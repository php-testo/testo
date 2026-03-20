<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

use Testo\Expect;
use Testo\Test;

/**
 * Stubs for negative {@see Expect::exception()->fromMethod()} scenarios.
 */
final class ExpectExceptionFromMethod
{
    /**
     * Wrong class — exception is created in ExceptionThrower, but we expect \stdClass.
     */
    #[Test]
    public function wrongClass(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(\stdClass::class, 'wrongClass');

        ExceptionThrower::throw(\RuntimeException::class);
    }

    /**
     * Wrong method — exception is created in ExceptionThrower::throw, but we expect 'nonExistent'.
     */
    #[Test]
    public function wrongMethod(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(ExceptionThrower::class, 'nonExistent');

        ExceptionThrower::throw(\RuntimeException::class);
    }

    /**
     * One of multiple fromMethod conditions doesn't match.
     */
    #[Test]
    public function multipleOneWrong(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(ExceptionThrower::class, 'throw')
            ->fromMethod(ExceptionThrower::class, 'nonExistent');

        ExceptionThrower::throw(\RuntimeException::class);
    }

    /**
     * The method exists in the codebase, but is not in the trace of this specific exception.
     */
    #[Test]
    public function methodNotInTrace(): never
    {
        Expect::exception(\RuntimeException::class)
            ->fromMethod(ExceptionThrower::class, 'deepThrow');

        ExceptionThrower::throw(\RuntimeException::class);
    }
}
