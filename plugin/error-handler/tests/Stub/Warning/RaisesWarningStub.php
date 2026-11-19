<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Stub\Warning;

use Testo\Assert;
use Testo\Test;

/**
 * Driven through {@see \Testo\Testing\Helper\TestRunner}; never discovered by the suite itself.
 */
final class RaisesWarningStub
{
    #[Test]
    public function raisesWarning(): void
    {
        \trigger_error('Stub warning', \E_USER_WARNING);

        Assert::true(true);
    }
}
