<?php

declare(strict_types=1);

namespace Testo\Event\TestSuite;

/**
 * Event triggered before a test suite starts executing.
 *
 * A test suite is a named, configured collection of test cases gathered from one or more files.
 * This event is fired once per suite, before any of its test cases are executed.
 *
 * @psalm-immutable
 * @api
 */
final readonly class TestSuiteStarting extends TestSuiteEvent {}
