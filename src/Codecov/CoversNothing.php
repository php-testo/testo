<?php

declare(strict_types=1);

namespace Testo\Codecov;

/**
 * Marks a test method or class as not contributing to code coverage.
 *
 * Coverage data from tests marked with this attribute is discarded —
 * the test still runs but does not affect coverage metrics.
 *
 * ```
 *  #[CoversNothing]
 *  public function smokeTest(): void
 *  {
 *      // This test exercises code but its coverage is not counted
 *  }
 * ```
 *
 * When placed on a class, applies to all test methods in that class.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
final readonly class CoversNothing implements Internal\CoverageAttribute {}
