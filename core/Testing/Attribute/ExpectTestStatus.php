<?php

declare(strict_types=1);

namespace Testo\Testing\Attribute;

use Testo\Core\Value\Status;

/**
 * Asserts that the {@see \Testo\Core\Context\TestResult} returned by
 * {@see \Testo\Testing\Helper\TestRunner::runTest()} has the expected {@see Status}.
 *
 * The test method must return the `TestResult` directly. If the expected status does not
 * match the stub's actual status, the outer test is marked {@see Status::Failed}.
 *
 * @see ExpectAssertionsCount
 * @see ExpectTestResultAttribute
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class ExpectTestStatus
{
    public function __construct(public Status $status) {}
}
