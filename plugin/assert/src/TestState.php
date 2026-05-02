<?php

declare(strict_types=1);

namespace Testo\Assert;

use Testo\Assert\State\Record;
use Testo\Core\Context\TestResult;

/**
 * Collects assertions.
 *
 * The state is stored as an attribute in the {@see TestResult}, and can be accessed by Event Listeners
 * and Interceptors.
 *
 * ```
 *  $testState = $result->getAttribute(TestState::class);
 * ```
 *
 * @api
 */
final class TestState
{
    /**
     * @var list<Record> The history of assertions.
     */
    public array $history = [];

    /**
     * @note that the expectation list will be processed in LIFO order.
     *
     * @var list<callable(TestResult, TestState): TestResult> List of expectation handlers.
     */
    public array $expectations = [];
}
