<?php

declare(strict_types=1);

namespace Tests\Testo\Self\TestCase;

use Testo\Assert;
use Testo\Attribute\Test;

/**
 * By default, the same test case instance is used for the each non-static test method.
 */
final class CaseInstantiatorCache
{
    private static bool $initialized = false;

    public function __construct()
    {
        // If this constructor is called more than once, the test case instance is reused.
        self::$initialized and throw new \RuntimeException('Test case instance reused.');

        self::$initialized = true;
    }

    #[Test]
    public function simpleStaticMethod(): void
    {
        Assert::true(true);
    }

    #[Test]
    public function anotherStaticMethod(): void
    {
        Assert::false(false);
    }
}
