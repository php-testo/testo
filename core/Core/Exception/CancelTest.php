<?php

declare(strict_types=1);

namespace Testo\Core\Exception;

use Testo\Core\Value\Status;

/**
 * Throw to mark the test as {@see Status::Cancelled} — the run was aborted
 * by a control-flow signal unrelated to the test's preconditions.
 *
 * Intended to be raised by cooperative infrastructure that delivers a cancellation
 * signal into the running test body — deadline helpers checked from inside the
 * test, Fiber-based async libraries unwinding a suspended test via
 * {@see \Fiber::throw()}, etc. Not a generic "I don't want to run" marker — for
 * that, throw {@see SkipTest}.
 *
 * Must escape the test method directly: only the test handler's try/catch maps
 * this exception to {@see Status::Cancelled}. Throws originating from interceptors
 * bubble out of the pipeline and are treated as a pipeline failure.
 */
class CancelTest extends \RuntimeException {}
