<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestCase;

use Testo\Test\Dto\CaseInfo;
use Testo\Test\Dto\CaseResult;

/**
 * Event triggered after the test case pipeline (case interceptors) has finished executing.
 *
 * This is the last event in the test case lifecycle, fired after all case interceptors have completed.
 *
 * @psalm-immutable
 */
final class TestCasePipelineFinished extends TestCaseEvent
{
    public function __construct(
        CaseInfo $caseInfo,
        public readonly CaseResult $caseResult,
    ) {
        parent::__construct($caseInfo);
    }
}
