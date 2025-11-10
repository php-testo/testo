<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestSuite;

/**
 * Event triggered before a test suite starts executing.
 *
 * A test suite represents a collection of test cases (test methods) from a single test class.
 * This event is fired once per test class, before any test cases are executed.
 *
 * @psalm-immutable
 */
final class TestSuiteStarting extends TestSuiteEvent {}
