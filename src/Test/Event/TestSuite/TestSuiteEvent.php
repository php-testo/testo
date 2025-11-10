<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestSuite;

use Testo\Test\Dto\SuiteInfo;

/**
 * Test suite related event.
 */
abstract class TestSuiteEvent
{
    public function __construct(
        public readonly SuiteInfo $suiteInfo,
    ) {}
}
