<?php

declare(strict_types=1);

namespace Testo\Event\Framework;

/**
 * Event triggered when a worker process finishes.
 *
 * Fired at the end of each subprocess after it has completed
 * executing its assigned subset of tests.
 *
 * @psalm-immutable
 */
final class WorkerFinished {}
