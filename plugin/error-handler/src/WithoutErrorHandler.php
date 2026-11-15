<?php

declare(strict_types=1);

namespace Testo\ErrorHandler;

use Testo\Core\Value\Status;

/**
 * Opts a test out of the error-handler plugin entirely: no capturing handler is installed around
 * it, nothing is recorded on the {@see \Testo\Core\Context\TestResult}, nothing reaches the
 * `stderr` channel, `failOnError` does not apply, and the handler stack the test leaves behind is
 * neither judged {@see Status::Risky} nor restored.
 *
 * For a test that needs PHP's error handling exactly as the process has it: one that asserts on
 * what `set_error_handler()` returns, walks the stack itself, or expects PHP's own printing.
 *
 * Takes precedence over {@see ExpectErrorHandlerChange} on the same test: with no handler of the
 * plugin's own in place there is no stack change to check, and the idle declaration is reported as
 * a warning on the `stderr` channel. On a class the attribute applies to every test of the class.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class WithoutErrorHandler {}
