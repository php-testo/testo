<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Stub;

use Testo\ErrorHandler\WithoutErrorHandler;

/**
 * Class-level {@see WithoutErrorHandler} reflection target; never run as a test.
 */
#[WithoutErrorHandler]
final class NoHandlerCase
{
    public function inherited(): void {}
}
