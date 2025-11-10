<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestSuite;

/**
 * Event triggered before the test suite pipeline (suite interceptors) starts executing.
 *
 * This is the first event in the test suite lifecycle, fired before any suite interceptors are invoked.
 *
 * @psalm-immutable
 */
final class TestSuitePipelineStarting extends TestSuiteEvent {}
