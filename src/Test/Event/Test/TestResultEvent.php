<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

use Testo\Test\Dto\TestInfo;
use Testo\Test\Dto\TestResult;

/**
 * Test result related event.
 */
abstract class TestResultEvent extends TestEvent
{
    public function __construct(
        TestInfo $testInfo,
        public readonly TestResult $testResult,
    ) {
        parent::__construct($testInfo);
    }
}
