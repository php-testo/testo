<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Internal\BenchHandler;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(BenchHandler::class)]
final class BenchMethodClosureTest
{
    public function aPrivateStaticMethodIsInvokable(): void
    {
        $fn = BenchHandler::methodClosure(self::subject()::class, 'tripled', null);

        Assert::same($fn(3), 9);
    }

    public function aPrivateInstanceMethodBindsToTheGivenInstance(): void
    {
        $subject = self::subject();
        $fn = BenchHandler::methodClosure($subject::class, 'doubled', $subject);

        Assert::same($fn(3), 6);
    }

    public function aNonStaticMethodWithoutAnInstanceIsRejected(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('without an instance');

        BenchHandler::methodClosure(self::subject()::class, 'doubled', null);
    }

    public function aNonStaticMethodWithAnInstanceOfAnotherClassIsRejected(): never
    {
        Expect::exception(\InvalidArgumentException::class);

        BenchHandler::methodClosure(self::subject()::class, 'doubled', new \stdClass());
    }

    private static function subject(): object
    {
        return new class {
            private static function tripled(int $x): int
            {
                return $x * 3;
            }

            private function doubled(int $x): int
            {
                return $x * 2;
            }
        };
    }
}
