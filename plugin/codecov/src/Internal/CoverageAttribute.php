<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

/**
 * Marker interface for coverage-related attributes.
 *
 * Used to fetch all coverage attributes ({@see \Testo\Codecov\Covers},
 * {@see \Testo\Codecov\CoversNothing}) from reflection in a single call.
 *
 * @internal
 */
interface CoverageAttribute {}
