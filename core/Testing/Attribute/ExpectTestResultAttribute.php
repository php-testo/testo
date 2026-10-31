<?php

declare(strict_types=1);

namespace Testo\Testing\Attribute;

/**
 * Asserts that the {@see \Testo\Core\Context\TestResult} returned by
 * {@see \Testo\Testing\Helper\TestRunner::runTest()} contains an attribute stored under
 * the given key. Repeatable — apply multiple times to check for several attributes.
 *
 * The test method must return the `TestResult` directly. If the attribute is absent, the
 * outer test is marked {@see \Testo\Core\Value\Status::Failed}.
 *
 * @see ExpectTestStatus
 * @see ExpectAssertionsCount
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
final readonly class ExpectTestResultAttribute
{
    /**
     * @param non-empty-string $name Attribute key to look up, typically a class-string.
     */
    public function __construct(public string $name) {}
}
