<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Stub;

use Testo\ErrorHandler\ExpectErrorHandlerChange;

/**
 * Reflection targets for the {@see ExpectErrorHandlerChange} lookup; never run as tests.
 */
final class HandlerChange
{
    #[ExpectErrorHandlerChange]
    public function declared(): void {}

    public function undeclared(): void {}
}
