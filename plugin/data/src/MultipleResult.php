<?php

declare(strict_types=1);

namespace Testo\Data;

use Testo\Core\Context\TestResult;

/**
 * Aggregate result for multiple test runs.
 *
 * This is used when a test is run multiple times because of Data Providers.
 *
 * The results are stored as an attribute in the {@see TestResult}, and can be accessed by Event Listeners
 * and Interceptors.
 *
 * ```
 *  $multipleResults = $testResult->getAttribute(MultipleResult::class);
 * ```
 *
 * @api
 */
final readonly class MultipleResult
{
    public function __construct(
        /**
         * @var non-empty-array<array-key, TestResult>
         */
        public array $results,
    ) {}
}
