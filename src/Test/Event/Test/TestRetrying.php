<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

use Testo\Test\Definition\TestDefinition;
use Testo\Test\Dto\TestInfo;
use Testo\Test\Dto\TestResult;

/**
 * Event triggered when a failed test is about to be retried.
 *
 * This event fires after a TestFinished event with a failed result, and before the next
 * TestStarting event for the retry attempt. It allows renderers to log retry decisions
 * and track retry attempts.
 *
 * @psalm-immutable
 */
final class TestRetrying extends TestEvent
{
    public function __construct(
        TestInfo $testInfo,

        /**
         * The current attempt number for this test execution (1-based).
         *
         * @var int<1, max>
         */
        public readonly int $attempt,

        /**
         * The result of the previous test run that is being retried.
         */
        public readonly TestResult $previousRunResult,
    ) {
        parent::__construct($testInfo);
    }
}
