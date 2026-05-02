<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Expect;
use Testo\Test;

/**
 * Assertion examples.
 */
final class AssertObject
{
    #[Test]
    public function instanceOf(): void
    {
        $obj = new \DateTimeImmutable();

        Assert::instanceOf($obj, \DateTimeInterface::class);
        Assert::instanceOf($obj, \DateTimeImmutable::class);

        Assert::object($obj)->instanceOf(\DateTimeInterface::class);
    }

    #[Test]
    public function hasProperty(): void
    {
        $obj = new class {
            private int $private = 42;
            public int $public = 42;
        };
        Assert::object($obj)->hasProperty('private');
        Assert::object($obj)->hasProperty('public');

        Expect::exception(AssertionException::class);
        Assert::object($obj)->hasProperty('wrongPropertyName');
    }
}
