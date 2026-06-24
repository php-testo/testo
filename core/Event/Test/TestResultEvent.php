<?php

declare(strict_types=1);

namespace Testo\Event\Test;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;

/**
 * Test result related event.
 *
 * @psalm-immutable
 * @api
 */
abstract readonly class TestResultEvent extends TestEvent
{
    public function __construct(
        TestInfo $testInfo,
        public TestResult $testResult,
    ) {
        parent::__construct($testInfo);
    }
}
