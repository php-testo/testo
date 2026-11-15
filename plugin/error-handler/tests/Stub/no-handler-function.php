<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Stub;

use Testo\ErrorHandler\WithoutErrorHandler;

/**
 * Function-level {@see WithoutErrorHandler} reflection target; never run as a test.
 */
#[WithoutErrorHandler]
function optedOutFunction(): void {}
