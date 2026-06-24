<?php

declare(strict_types=1);

namespace Testo\Core\Exception;

use Testo\Core\Value\Status;

/**
 * Throw from the test body to mark the test as {@see Status::Skipped}.
 *
 * Use when the test is not applicable in the current environment / configuration
 * and therefore cannot produce a meaningful verdict — missing extension, wrong SAPI,
 * disabled feature flag, unavailable optional dependency, etc.
 *
 * Must escape the test method directly: only the test handler's try/catch maps
 * this exception to {@see Status::Skipped}. Throws originating from interceptors
 * (lifecycle hooks, data providers, etc.) bubble out of the pipeline and are
 * treated as a pipeline failure, not a skip — those layers are responsible for
 * producing their own {@see \Testo\Core\Context\TestResult} if they want to skip.
 *
 * For external interruption signals (deadline, fail-fast, cooperative shutdown)
 * use {@see CancelTest} instead.
 *
 * @api Subclasses are recognized too: the runner maps any `instanceof SkipTest` throw to {@see Status::Skipped}.
 */
class SkipTest extends \RuntimeException {}
