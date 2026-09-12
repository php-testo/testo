<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Skip;

/**
 * Carries a class-level `#[Skip]` for {@see SkipTraitStub} to use — nothing else.
 */
#[Skip('inherited from the trait')]
trait SkipMarkerTrait {}
