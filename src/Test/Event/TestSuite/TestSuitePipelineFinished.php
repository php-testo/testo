<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestSuite;

use Testo\Test\Dto\SuiteInfo;
use Testo\Test\Dto\SuiteResult;

/**
 * Event triggered after the test suite pipeline (suite interceptors) has finished executing.
 *
 * This is the last event in the test suite lifecycle, fired after all suite interceptors have completed.
 *
 * @psalm-immutable
 */
final class TestSuitePipelineFinished extends TestSuiteEvent
{
    public function __construct(
        SuiteInfo $suiteInfo,
        public readonly SuiteResult $suiteResult,
    ) {
        parent::__construct($suiteInfo);
    }
}
