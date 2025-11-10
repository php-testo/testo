<?php

declare(strict_types=1);

namespace Testo\Test\Event\TestCase;

/**
 * Event triggered before the test case pipeline (case interceptors) starts executing.
 *
 * This is the first event in the test case lifecycle, fired before any case interceptors are invoked.
 *
 * @psalm-immutable
 */
final class TestCasePipelineStarting extends TestCaseEvent {}
