<?php

declare(strict_types=1);

namespace Testo\ErrorHandler\Exception;

use Testo\ErrorHandler\ExpectErrorHandlerChange;

/**
 * A test declares {@see ExpectErrorHandlerChange} but left the error-handler stack as it found it.
 *
 * @api
 */
final class ErrorHandlerUnchanged extends \LogicException
{
    public function __construct()
    {
        parent::__construct('The test declares #[ExpectErrorHandlerChange] but left the error-handler stack unchanged.');
    }
}
