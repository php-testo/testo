<?php

declare(strict_types=1);

namespace Testo\Event\TestCase;

/**
 * Event triggered before a test case starts executing.
 *
 * A test case is the file-level scope that groups tests: the methods of a single test class,
 * or the functions of a single file. A file with several test classes yields several test cases.
 * This event is fired once per case, before any of its tests run.
 *
 * @psalm-immutable
 * @api
 */
final readonly class TestCaseStarting extends TestCaseEvent {}
