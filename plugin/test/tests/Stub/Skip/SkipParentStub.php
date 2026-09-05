<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Test\Skip;

/**
 * Abstract, so the locator never discovers it as its own case — only the child inherits
 * the class-level `#[Skip]`.
 */
#[Skip('inherited from the parent class')]
abstract class SkipParentStub {}
