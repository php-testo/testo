<?php

declare(strict_types=1);

namespace Testo\Event\TestCase;

use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;

/**
 * Event triggered after a test case has finished executing.
 *
 * Fired once per case, after all of its tests have completed.
 * It contains the aggregated result of all tests within the case.
 *
 * @psalm-immutable
 * @api
 */
final readonly class TestCaseFinished extends TestCaseEvent
{
    public function __construct(
        CaseInfo $caseInfo,
        public CaseResult $caseResult,
    ) {
        parent::__construct($caseInfo);
    }
}
