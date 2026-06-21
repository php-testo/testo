<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Concrete subclass that inherits its test method from {@see InheritedTestBase}
 * without overriding it. The runtime test identity must resolve to this class,
 * not to the abstract declaring base.
 */
final class InheritedTestChild extends InheritedTestBase {}
