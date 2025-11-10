<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestCase;

/**
 * Event triggered before a test case starts executing.
 *
 * A test case represents a single test method that may contain multiple test runs
 * (via DataProvider) or retry attempts. This event is fired once per test method,
 * before any batches or individual test runs.
 *
 * @psalm-immutable
 */
final class TestCaseStarting extends TestCaseEvent {}
