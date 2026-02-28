<?php

declare(strict_types=1);

namespace Testo\Event\Framework;

use Testo\Application\Value\RunResult;

/**
 * Event triggered when the testing session finishes.
 *
 * Fired once after all test suites, cases, and tests have been executed.
 * Carries the complete collection of results for the entire test run.
 *
 * @psalm-immutable
 */
final class SessionFinished
{
    public function __construct(
        public readonly RunResult $result,
    ) {}
}
