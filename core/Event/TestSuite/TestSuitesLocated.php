<?php

declare(strict_types=1);

namespace Testo\Event\TestSuite;

use Testo\Application\Config\SuiteConfig;

/**
 * Event triggered once the run knows which test suites it will go through, before the first of them is
 * scanned.
 *
 * The list is final: suite filters such as `--suite` have already been applied, and suites run in this
 * order. A suite whose scan finds no test cases is skipped without a {@see TestSuiteStarting}, so the list
 * is an upper bound of the suites that actually start.
 *
 * @psalm-immutable
 * @api
 */
final readonly class TestSuitesLocated
{
    /**
     * @param list<SuiteConfig> $suites
     */
    public function __construct(
        public array $suites,
    ) {}
}
