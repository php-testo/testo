<?php

declare(strict_types=1);

namespace Testo\Event\TestSuite;

use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;

/**
 * Event triggered after a test suite has finished executing.
 *
 * Fired once per suite, after all of its test cases have completed.
 * It contains the aggregated result of every test case in the suite.
 *
 * @psalm-immutable
 * @api
 */
final readonly class TestSuiteFinished extends TestSuiteEvent
{
    public function __construct(
        SuiteInfo $suiteInfo,
        public SuiteResult $suiteResult,
    ) {
        parent::__construct($suiteInfo);
    }
}
