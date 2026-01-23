<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Self;

use Testo\Application\Attribute\Test;
use Testo\Assert;
use Testo\Lifecycle\BeforeEach;

/**
 * Self-tests for lifecycle method priority ordering.
 *
 * Higher priority methods are executed first.
 */
final class PriorityTest
{
    /** @var list<string> */
    private array $beforeLog = [];

    #[BeforeEach(priority: 10)]
    public function highPriorityBefore(): void
    {
        $this->beforeLog[] = 'high';
    }

    #[BeforeEach(priority: 0)]
    public function defaultPriorityBefore(): void
    {
        $this->beforeLog[] = 'default';
    }

    #[BeforeEach(priority: -10)]
    public function lowPriorityBefore(): void
    {
        $this->beforeLog[] = 'low';
    }

    #[Test]
    public function beforeMethodsRunInPriorityOrder(): void
    {
        // Higher priority runs first
        // Find the indexes
        $highIndex = \array_search('high', $this->beforeLog, true);
        $defaultIndex = \array_search('default', $this->beforeLog, true);
        $lowIndex = \array_search('low', $this->beforeLog, true);

        // Verify order: high < default < low (by index)
        Assert::true($highIndex < $defaultIndex);
        Assert::true($defaultIndex < $lowIndex);
    }
}
