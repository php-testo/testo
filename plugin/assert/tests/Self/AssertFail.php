<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Test\Fail;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::fail()
 */
#[Test]
#[Covers(Assert::class, 'fail')]
final class AssertFail
{
    /**
     * Assert::fail() throws a {@see Fail} carrying the given message. Expecting that
     * exception lets the test pass while still exercising the failure path.
     */
    public function throwsFailWithMessage(): never
    {
        Expect::exception(Fail::class)
            ->withMessageContaining('deliberate failure');
        Assert::fail('deliberate failure');
    }
}
