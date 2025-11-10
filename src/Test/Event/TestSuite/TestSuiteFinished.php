<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestSuite;

use Testo\Test\Dto\SuiteInfo;
use Testo\Test\Dto\SuiteResult;

/**
 * Event triggered after a test suite has finished executing.
 *
 * This event is fired once per test class, after all test cases have completed.
 * It contains the aggregated result of all test cases within the suite.
 *
 * @psalm-immutable
 */
final class TestSuiteFinished extends TestSuiteEvent
{
    public function __construct(
        SuiteInfo $suiteInfo,
        public readonly SuiteResult $suiteResult,
    ) {
        parent::__construct($suiteInfo);
    }
}
