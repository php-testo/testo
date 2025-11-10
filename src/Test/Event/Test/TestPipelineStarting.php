<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

/**
 * Event triggered before the test pipeline (run interceptors) starts executing.
 *
 * This is the first event in the test lifecycle, fired before any run interceptors are invoked.
 *
 * @psalm-immutable
 */
final class TestPipelineStarting extends TestEvent {}
