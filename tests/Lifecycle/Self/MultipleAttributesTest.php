<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Self;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Lifecycle\AfterEach;
use Testo\Lifecycle\BeforeEach;

/**
 * Self-tests for multiple Before/After methods on a single class.
 */
final class MultipleAttributesTest
{
    /** @var list<string> */
    private array $log = [];

    #[BeforeEach]
    public function firstBefore(): void
    {
        $this->log[] = 'before-1';
    }

    #[BeforeEach]
    public function secondBefore(): void
    {
        $this->log[] = 'before-2';
    }

    #[AfterEach]
    public function firstAfter(): void
    {
        $this->log[] = 'after-1';
    }

    #[AfterEach]
    public function secondAfter(): void
    {
        $this->log[] = 'after-2';
    }

    #[Test]
    public function multipleBeforeMethodsAreCalled(): void
    {
        // Both before methods should have been called
        Assert::true(\in_array('before-1', $this->log, true));
        Assert::true(\in_array('before-2', $this->log, true));
    }
}
