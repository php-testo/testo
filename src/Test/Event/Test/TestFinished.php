<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

/**
 * Event triggered immediately after a single test execution completes.
 *
 * This event fires for each individual test run, regardless of the result (success, failure, skipped).
 * It contains the test result and is fired before any retry logic is applied.
 *
 * @psalm-immutable
 */
final class TestFinished extends TestResultEvent {}
