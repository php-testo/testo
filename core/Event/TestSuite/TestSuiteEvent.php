<?php

declare(strict_types=1);

namespace Testo\Event\TestSuite;

use Testo\Core\Context\SuiteInfo;

/**
 * Test suite related event.
 *
 * @psalm-immutable
 * @api
 */
abstract readonly class TestSuiteEvent
{
    public function __construct(
        public SuiteInfo $suiteInfo,
    ) {}
}
