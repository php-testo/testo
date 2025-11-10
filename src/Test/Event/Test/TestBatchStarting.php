<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

/**
 * Event triggered when a batch of test runs is about to start.
 *
 * A batch represents multiple executions of the same test, typically when:
 * - A DataProvider is used (test runs for each data set)
 * - Retry policy is active (test may run multiple times on failure)
 *
 * This event signals renderers to open a new nesting level to group individual test runs.
 *
 * @psalm-immutable
 */
final class TestBatchStarting extends TestEvent {}
