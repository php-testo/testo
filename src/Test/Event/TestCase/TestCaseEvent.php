<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestCase;

use Testo\Test\Dto\CaseInfo;

/**
 * Test case related event.
 */
abstract class TestCaseEvent
{
    public function __construct(
        public readonly CaseInfo $caseInfo,
    ) {}
}
