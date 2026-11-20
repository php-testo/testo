<?php

declare(strict_types=1);

namespace Testo\Testing\Attribute;

/**
 * Asserts that the {@see \Testo\Core\Context\TestResult} returned by
 * {@see \Testo\Testing\Helper\TestRunner::runTest()} recorded exactly the given number of
 * assertions (the `assertions` metric contributed by the Assert plugin).
 *
 * The test method must return the `TestResult` directly. If the count does not match, the
 * outer test is marked {@see \Testo\Core\Value\Status::Failed}.
 *
 * @see ExpectTestStatus
 * @see ExpectTestResultAttribute
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class ExpectAssertionsCount
{
    /**
     * @param int<0, max> $count Expected number of assertions.
     */
    public function __construct(public int $count) {}
}
