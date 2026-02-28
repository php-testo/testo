<?php

declare(strict_types=1);

namespace Testo\Event\Framework;

/**
 * Event triggered when a worker process starts.
 *
 * Fired at the beginning of each subprocess responsible for executing
 * a subset of tests in parallel or isolated mode.
 *
 * @psalm-immutable
 */
final class WorkerStarting {}
