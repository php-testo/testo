<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Stub;

use Testo\ErrorHandler\ExpectErrorHandlerChange;
use Testo\ErrorHandler\WithoutErrorHandler;

/**
 * Reflection targets for the {@see WithoutErrorHandler} lookup; never run as tests.
 */
final class NoHandler
{
    #[WithoutErrorHandler]
    public function optedOut(): void {}

    #[WithoutErrorHandler]
    #[ExpectErrorHandlerChange]
    public function optedOutAndDeclaringChange(): void {}
}
