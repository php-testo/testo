<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Assert\State\Expectation;
use Testo\Assert\State\Assertion\AssertionSuccess;
use Testo\Assert\TestState;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Assert\Stub\CustomAssertions;

#[Test]
#[TestingSuite(path: __DIR__ . '/../Stub')]
final class CustomAssertionsTest
{
    public function successRecordsThePublicCustomAssertion(): void
    {
        $result = TestRunner::runTest([CustomAssertions::class, 'success']);
        Assert::same($result->status, Status::Passed);
        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, 1);
        Assert::instanceOf($state->history[0], AssertionSuccess::class);
        Assert::same($state->history[0]->getValue(), 'array(1)');
        Assert::same($state->history[0]->getAssertion(), 'is valid');
        Assert::same($state->history[0]->getContext(), 'domain check');
    }

    public function mixedBuiltInAndCustomHistoryPreservesOrder(): void
    {
        $result = TestRunner::runTest([CustomAssertions::class, 'mixedHistory']);
        Assert::same($result->status, Status::Passed);
        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, 3);
        Assert::same($state->history[0]->getAssertion(), 'is the same');
        Assert::same($state->history[1]->getAssertion(), 'is custom');
        Assert::same($state->history[2]->getAssertion(), 'is exactly `true`');
    }

    public function uncaughtFailureUsesNormalFailurePath(): void
    {
        $result = TestRunner::runTest([CustomAssertions::class, 'uncaughtFailure']);
        Assert::same($result->status, Status::Failed);
        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, 1);
        Assert::same($state->history[0], $result->failure);
        Assert::same($state->history[0]->getAssertion(), 'is valid');
        Assert::same($state->history[0]->getContext(), 'domain check');
        Assert::same($state->history[0]->getFailReason(), 'invalid');
        Assert::same($state->history[0]->getFailDetails(), 'details');
        Assert::string($result->failure?->getMessage())
            ->contains('Failed assertion that `value` is valid: invalid.')
            ->contains('Meaning: domain check')
            ->contains("Details:\ndetails");
        Assert::true($result->failure?->getTrace() !== []);
    }

    public function caughtFailureIsRetainedAndTestPasses(): void
    {
        $result = TestRunner::runTest([CustomAssertions::class, 'caughtFailure']);
        Assert::same($result->status, Status::Passed);
        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, 1);
        Assert::same($state->history[0], CustomAssertions::$caughtFailure);
        Assert::instanceOf($state->history[0], AssertionException::class);
    }

    public function expectedComparisonFailureUsesNormalExpectationHandling(): void
    {
        $result = TestRunner::runTest([CustomAssertions::class, 'expectedComparisonFailure']);
        Assert::same($result->status, Status::Passed);
        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, 2);

        $comparisonFailure = null;
        $expectation = null;
        foreach ($state->history as $record) {
            $record instanceof ComparisonFailure and $comparisonFailure = $record;
            $record instanceof Expectation and $expectation = $record;
        }

        Assert::instanceOf($comparisonFailure, ComparisonFailure::class);
        Assert::same($comparisonFailure->expected, 'expected');
        Assert::same($comparisonFailure->actual, 'actual');
        Assert::instanceOf($expectation, Expectation::class);
    }

    public function emptySuccessDescriptionIsRejected(): void
    {
        $this->assertRejectedInput('emptySuccessDescriptionIsRejected');
    }

    public function emptyAssertionExceptionDescriptionIsRejected(): void
    {
        $this->assertRejectedInput('emptyAssertionExceptionDescriptionIsRejected');
    }

    public function unsupportedThrowableIsRejected(): void
    {
        $this->assertRejectedInput('unsupportedThrowableIsRejected');
    }

    public function emptyCustomAssertionDescriptionIsRejected(): void
    {
        $this->assertRejectedInput('emptyCustomAssertionDescriptionIsRejected');
    }

    public function validSuccessAfterValidationIsRecorded(): void
    {
        $result = TestRunner::runTest([CustomAssertions::class, 'validSuccessAfterValidation']);
        Assert::same($result->status, Status::Passed);
        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, 1);
        Assert::instanceOf($state->history[0], AssertionSuccess::class);
        Assert::same($state->history[0]->getAssertion(), 'after validation');
    }

    private function assertRejectedInput(string $fixture): void
    {
        $result = TestRunner::runTest([CustomAssertions::class, $fixture]);
        Assert::same($result->status, Status::Passed);
        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, 1);
        Assert::instanceOf($state->history[0], Expectation::class);
    }

    public static bool $outsideStateIsSafe = false;

    public function outsideActiveStateKeepsExplicitBoundary(): void
    {
        Assert::true(self::$outsideStateIsSafe);
    }

}

CustomAssertionsTest::$outsideStateIsSafe = (static function (): bool {
    Assert::success('value', 'outside');
    $failure = new AssertionException('value', 'fails', '', 'reason', '');
    try {
        Assert::failure($failure);
    } catch (AssertionException $actual) {
        return $actual === $failure;
    }

    return false;
})();
