<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

/**
 * Event triggered immediately before a single test execution starts.
 *
 * This event fires for each individual test run, whether it's:
 * - A standalone test
 * - A test run with a specific data set from a DataProvider
 * - A retry attempt
 *
 * @psalm-immutable
 */
final class TestStarting extends TestEvent {}
