<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Self;

use Testo\Application\Attribute\Test;
use Testo\Assert;
use Testo\Lifecycle\AfterEach;
use Testo\Lifecycle\BeforeEach;

/**
 * Self-tests for Before and After lifecycle attributes.
 */
final class BeforeAfterTest
{
    /** @var list<string> */
    private array $executionLog = [];

    #[BeforeEach]
    public function setupFirst(): void
    {
        $this->executionLog[] = 'before';
    }

    #[AfterEach]
    public function teardownFirst(): void
    {
        $this->executionLog[] = 'after';
    }

    #[Test]
    public function beforeIsCalledBeforeTest(): void
    {
        Assert::true(\in_array('before', $this->executionLog, true));
    }

    #[Test]
    public function executionLogContainsBefore(): void
    {
        // Before method should have added 'before' to the log
        Assert::true(\in_array('before', $this->executionLog, true));
    }
}
