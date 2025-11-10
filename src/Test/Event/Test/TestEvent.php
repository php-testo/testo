<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

use Testo\Test\Dto\TestInfo;

/**
 * Test related event.
 */
abstract class TestEvent
{
    public function __construct(
        public readonly TestInfo $testInfo,
    ) {}
}
