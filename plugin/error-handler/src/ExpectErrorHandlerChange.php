<?php

declare(strict_types=1);

namespace Testo\ErrorHandler;

use Testo\Core\Value\Status;

/**
 * Declares that a test intentionally changes the PHP error-handler stack and leaves the change in
 * place: it installs a handler it does not remove, or removes one it did not install.
 *
 * The attribute is a two-way contract checked at the end of the run:
 *
 * - a test marked with it that changes the stack stays {@see Status::Passed} (without it such a test
 *   is reported as {@see Status::Risky}, as its handler would shadow the ones of the tests after it);
 * - a test marked with it that leaves the stack untouched is {@see Status::Failed} with
 *   {@see Exception\ErrorHandlerUnchanged}, because the declaration no longer holds.
 *
 * The stack is restored to its pre-test state afterwards in either case. On a class the attribute
 * applies to every test of the class.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class ExpectErrorHandlerChange {}
