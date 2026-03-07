<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Self;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Lifecycle\AfterAll;
use Testo\Lifecycle\BeforeAll;

/**
 * Self-tests for BeforeAll and AfterAll lifecycle attributes.
 */
final class BeforeAfterAllTest
{
    /** @var list<string> */
    public static array $log = [];

    #[BeforeAll]
    public static function setupOnce(): void
    {
        self::$log[] = 'beforeAll';
    }

    #[AfterAll]
    public static function teardownOnce(): void
    {
        self::$log[] = 'afterAll';
    }

    #[Test]
    public function firstTest(): void
    {
        // BeforeAll should have been called once
        Assert::true(\in_array('beforeAll', self::$log, true));
        self::$log[] = 'test1';
    }

    #[Test]
    public function secondTest(): void
    {
        // BeforeAll should still be called only once (not twice)
        $beforeAllCount = \array_count_values(self::$log)['beforeAll'] ?? 0;
        Assert::same(1, $beforeAllCount);
        self::$log[] = 'test2';
    }
}
