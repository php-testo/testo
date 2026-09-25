<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Stub;

use Testo\ErrorHandler\ExpectErrorHandlerChange;

/**
 * Class-level {@see ExpectErrorHandlerChange} reflection target; never run as a test.
 */
#[ExpectErrorHandlerChange]
final class HandlerChangeCase
{
    public function inherited(): void {}
}
