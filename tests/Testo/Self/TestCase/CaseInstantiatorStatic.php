<?php

declare(strict_types=1);

namespace Tests\Testo\Self\TestCase;

use Testo\Assert;
use Testo\Attribute\Test;

/**
 * If there are static methods only in the test case, Testo must not try to instantiate the class.
 */
final class CaseInstantiatorStatic
{
    public function __construct()
    {
        throw new \LogicException('Constructor must not be called.');
    }

    #[Test]
    public static function simpleStaticMethod(): void
    {
        Assert::true(true);
    }

    #[Test]
    public static function anotherStaticMethod(): void
    {
        Assert::false(false);
    }
}
