<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Expectation;

use Testo\Assert\State\Test\Fail;
use Testo\Assert\TestState;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;

/**
 * If {@see Assert::fail()} was called but the exception was caught in the test and the test
 * ended successfully without throwing this is suspicious behavior, mark the test as Risky.
 *
 * todo: create Fail exception
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class ExpectedFail
{
    public function __construct(
        public Fail $fail,
    ) {}

    public function __invoke(TestResult $result, TestState $state): TestResult
    {

        // todo: add warning that the Fail has not been thrown
        // $this->fail !== $result->failure and $result = $result->withWarning($this->fail);

        return $result->status->isCompleted() && $this->fail !== $result->failure
            ? $result->with(status: Status::Risky)->withFailure($this->fail)
            : $result;
    }
}
