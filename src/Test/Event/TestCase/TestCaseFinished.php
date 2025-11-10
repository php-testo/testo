<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestCase;

use Testo\Test\Dto\CaseInfo;
use Testo\Test\Dto\CaseResult;

/**
 * Event triggered after a test case has finished executing.
 *
 * This event is fired once per test method, after all batches and individual test runs
 * have completed. It contains the aggregated result of all test runs within the case.
 *
 * @psalm-immutable
 */
final class TestCaseFinished extends TestCaseEvent
{
    public function __construct(
        CaseInfo $caseInfo,
        public readonly CaseResult $caseResult,
    ) {
        parent::__construct($caseInfo);
    }
}
