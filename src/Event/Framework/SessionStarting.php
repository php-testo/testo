<?php

declare(strict_types=1);

namespace Testo\Event\Framework;

/**
 * Event triggered when the testing session starts.
 *
 * Fired once before any test suites, cases, or tests are discovered and executed.
 * Represents the outermost lifecycle boundary of a complete test run.
 *
 * @psalm-immutable
 */
final class SessionStarting {}
